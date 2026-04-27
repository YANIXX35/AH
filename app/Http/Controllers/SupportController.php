<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupportController extends Controller
{
    /**
     * Centre d'aide (FAQ) et accès au support.
     */
    public function index()
    {
        return view('support.index', [
            'faq' => config('support.faq', []),
        ]);
    }

    public function tickets()
    {
        $tickets = SupportTicket::where('user_id', Auth::id())
            ->with('latestMessage')
            ->latest('updated_at')
            ->paginate(12);

        return view('support.tickets', compact('tickets'));
    }

    public function create()
    {
        return view('support.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $userId = Auth::id();

        DB::transaction(function () use ($validated, $userId) {
            $ticket = SupportTicket::create([
                'user_id' => $userId,
                'subject' => $validated['subject'],
                'status' => SupportTicket::STATUS_OPEN,
            ]);

            SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $userId,
                'body' => $validated['message'],
                'is_staff_reply' => false,
            ]);

            AppNotification::create([
                'user_id' => $userId,
                'title' => 'Demande support enregistrée',
                'body' => 'Votre message a bien été transmis. Nous vous répondrons dans les meilleurs délais.',
                'type' => 'success',
                'action_url' => route('support.tickets.show', $ticket),
            ]);

            $admins = \App\Models\User::query()
                ->where('is_platform_admin', true)
                ->get(['id']);

            foreach ($admins as $admin) {
                AppNotification::create([
                    'user_id' => $admin->id,
                    'title' => 'Nouveau ticket support client',
                    'body' => 'Nouveau ticket: '.$ticket->subject,
                    'type' => 'warning',
                    'action_url' => route('admin.support.tickets.show', $ticket),
                ]);
            }
        });

        return redirect()
            ->route('support.tickets')
            ->with('status', 'Votre demande a été envoyée au support.');
    }

    public function show(SupportTicket $ticket)
    {
        $this->authorizeTicket($ticket);
        $this->markIncomingAsDeliveredForClient($ticket);
        $this->markIncomingAsReadForClient($ticket);

        $ticket->load(['messages.user']);

        return view('support.show', compact('ticket'));
    }

    /**
     * Flux JSON pour rafraîchir les messages sans rechargement.
     */
    public function feed(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicket($ticket);
        $this->markIncomingAsDeliveredForClient($ticket);

        $afterId = (int) $request->query('after_id', 0);
        $messages = SupportMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('id', '>', $afterId)
            ->with('user:id,name')
            ->orderBy('id')
            ->get();

        $receipts = SupportMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('is_staff_reply', false)
            ->where('user_id', Auth::id())
            ->whereNotNull('delivered_at')
            ->get(['id', 'delivered_at', 'read_at'])
            ->map(fn (SupportMessage $message) => [
                'id' => $message->id,
                'delivery_state' => $message->deliveryState(),
            ])->values();

        return response()->json([
            'ticket_status' => $ticket->status,
            'messages' => $messages->map(fn (SupportMessage $message) => [
                'id' => $message->id,
                'body' => (string) $message->body,
                'is_staff_reply' => (bool) $message->is_staff_reply,
                'author' => $message->is_staff_reply
                    ? 'Équipe '.config('app.name')
                    : ($message->user?->name ?: 'Vous'),
                'created_at' => $message->created_at?->format('d/m/Y H:i'),
                'delivery_state' => $message->deliveryState(),
            ])->values(),
            'receipts' => $receipts,
        ]);
    }

    /**
     * Flux SSE pour mise a jour temps reel du ticket client.
     */
    public function stream(Request $request, SupportTicket $ticket): StreamedResponse
    {
        $this->authorizeTicket($ticket);
        $this->markIncomingAsDeliveredForClient($ticket);

        $afterId = (int) $request->query('after_id', 0);

        return response()->stream(function () use ($ticket, $afterId): void {
            @set_time_limit(0);
            $lastId = $afterId;
            $start = time();

            while ((time() - $start) < 25) {
                $messages = SupportMessage::query()
                    ->where('support_ticket_id', $ticket->id)
                    ->where('id', '>', $lastId)
                    ->with('user:id,name')
                    ->orderBy('id')
                    ->get();

                if ($messages->isNotEmpty()) {
                    $receipts = SupportMessage::query()
                        ->where('support_ticket_id', $ticket->id)
                        ->where('is_staff_reply', false)
                        ->where('user_id', Auth::id())
                        ->whereNotNull('delivered_at')
                        ->get(['id', 'delivered_at', 'read_at'])
                        ->map(fn (SupportMessage $message) => [
                            'id' => $message->id,
                            'delivery_state' => $message->deliveryState(),
                        ])->values();

                    $payload = [
                        'ticket_status' => $ticket->fresh()?->status ?? $ticket->status,
                        'messages' => $messages->map(function (SupportMessage $message) {
                            return [
                                'id' => $message->id,
                                'body' => (string) $message->body,
                                'is_staff_reply' => (bool) $message->is_staff_reply,
                                'author' => $message->is_staff_reply
                                    ? 'Équipe '.config('app.name')
                                    : ($message->user?->name ?: 'Vous'),
                                'created_at' => $message->created_at?->format('d/m/Y H:i'),
                                'delivery_state' => $message->deliveryState(),
                            ];
                        })->values(),
                        'receipts' => $receipts,
                    ];

                    echo "event: messages\n";
                    echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";

                    $lastId = (int) $messages->max('id');
                } else {
                    $receipts = SupportMessage::query()
                        ->where('support_ticket_id', $ticket->id)
                        ->where('is_staff_reply', false)
                        ->where('user_id', Auth::id())
                        ->whereNotNull('delivered_at')
                        ->get(['id', 'delivered_at', 'read_at'])
                        ->map(fn (SupportMessage $message) => [
                            'id' => $message->id,
                            'delivery_state' => $message->deliveryState(),
                        ])->values();
                    echo "event: receipts\n";
                    echo 'data: '.json_encode(['receipts' => $receipts], JSON_UNESCAPED_UNICODE)."\n\n";
                }

                @ob_flush();
                @flush();
                usleep(2000000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function storeMessage(Request $request, SupportTicket $ticket)
    {
        $this->authorizeTicket($ticket);

        if ($ticket->status === SupportTicket::STATUS_CLOSED) {
            return back()->withErrors(['message' => 'Ce fil est clos. Ouvrez une nouvelle demande si besoin.']);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'body' => $validated['message'],
            'is_staff_reply' => false,
        ]);

        $ticket->touch();

        $targetIds = [];
        if ($ticket->assigned_to_user_id) {
            $targetIds[] = (int) $ticket->assigned_to_user_id;
        }
        if (empty($targetIds)) {
            $targetIds = \App\Models\User::query()
                ->where('is_platform_admin', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        foreach (array_unique($targetIds) as $targetId) {
            AppNotification::create([
                'user_id' => $targetId,
                'title' => 'Nouveau message client sur ticket',
                'body' => 'Le client a repondu sur: '.$ticket->subject,
                'type' => 'info',
                'action_url' => route('admin.support.tickets.show', $ticket),
            ]);
        }

        return back()->with('status', 'Message ajouté.');
    }

    public function markRead(SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicket($ticket);
        $this->markIncomingAsReadForClient($ticket);

        return response()->json(['ok' => true]);
    }

    private function authorizeTicket(SupportTicket $ticket): void
    {
        if ((int) $ticket->user_id !== (int) Auth::id()) {
            abort(403);
        }
    }

    private function markIncomingAsDeliveredForClient(SupportTicket $ticket): void
    {
        SupportMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('is_staff_reply', true)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => now()]);
    }

    private function markIncomingAsReadForClient(SupportTicket $ticket): void
    {
        SupportMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('is_staff_reply', true)
            ->whereNotNull('delivered_at')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
