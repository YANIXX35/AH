<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', 'all');
        $assignee = (int) $request->query('assignee', 0);

        $query = SupportTicket::query()
            ->with(['user:id,name,email', 'assignedTo:id,name,email', 'latestMessage'])
            ->latest('updated_at');

        if ($status !== 'all' && in_array($status, [
            SupportTicket::STATUS_OPEN,
            SupportTicket::STATUS_IN_PROGRESS,
            SupportTicket::STATUS_CLOSED,
        ], true)) {
            $query->where('status', $status);
        }

        if ($assignee > 0) {
            $query->where('assigned_to_user_id', $assignee);
        }

        $tickets = $query->paginate(20)->withQueryString();

        $assignableUsers = User::query()
            ->where(function ($q) {
                $q->where('is_platform_admin', true)
                    ->orWhere('is_accountant', true);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.support.tickets', [
            'tickets' => $tickets,
            'status' => $status,
            'assignee' => $assignee,
            'assignableUsers' => $assignableUsers,
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        $this->markIncomingAsDeliveredForStaff($ticket);
        $this->markIncomingAsReadForStaff($ticket);
        $ticket->load(['user:id,name,email', 'assignedTo:id,name,email', 'messages.user:id,name,email']);

        $assignableUsers = User::query()
            ->where(function ($q) {
                $q->where('is_platform_admin', true)
                    ->orWhere('is_accountant', true);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.support.show', [
            'ticket' => $ticket,
            'assignableUsers' => $assignableUsers,
        ]);
    }

    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $assignee = User::query()->findOrFail((int) $data['assigned_to_user_id']);
        if (! $assignee->is_platform_admin && ! ($assignee->is_accountant ?? false)) {
            return back()->withErrors([
                'assigned_to_user_id' => 'Le ticket doit etre attribue a un administrateur plateforme ou un comptable.',
            ]);
        }

        $ticket->update([
            'assigned_to_user_id' => $assignee->id,
            'assigned_by_user_id' => $request->user()->id,
            'status' => $ticket->status === SupportTicket::STATUS_CLOSED ? SupportTicket::STATUS_OPEN : SupportTicket::STATUS_IN_PROGRESS,
        ]);

        AppNotification::query()->create([
            'user_id' => $assignee->id,
            'title' => 'Nouveau ticket support attribue',
            'body' => 'Le ticket "'.$ticket->subject.'" vous a ete assigne.',
            'type' => 'info',
            'action_url' => route('admin.support.tickets.show', $ticket),
        ]);

        return back()->with('status', 'Ticket attribue a '.$assignee->name.'.');
    }

    public function storeMessage(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $data['message'],
            'is_staff_reply' => true,
        ]);

        $ticket->update([
            'status' => SupportTicket::STATUS_IN_PROGRESS,
        ]);
        $ticket->touch();

        AppNotification::query()->create([
            'user_id' => $ticket->user_id,
            'title' => 'Reponse du support',
            'body' => 'Une reponse a ete ajoutee a votre ticket: '.$ticket->subject,
            'type' => 'info',
            'action_url' => route('support.tickets.show', $ticket),
        ]);

        return back()->with('status', 'Reponse envoyee au client.');
    }

    /**
     * Flux JSON admin pour rafraichir le chat en quasi temps reel.
     */
    public function feed(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->markIncomingAsDeliveredForStaff($ticket);
        $afterId = (int) $request->query('after_id', 0);
        $messages = SupportMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('id', '>', $afterId)
            ->with('user:id,name')
            ->orderBy('id')
            ->get();

        $receipts = SupportMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('is_staff_reply', true)
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
                    ? (($message->user?->name ?: 'Staff').' (support)')
                    : ($message->user?->name ?: 'Client'),
                'created_at' => $message->created_at?->format('d/m/Y H:i'),
                'delivery_state' => $message->deliveryState(),
            ])->values(),
            'receipts' => $receipts,
        ]);
    }

    /**
     * Flux SSE admin pour conversation ticket en temps reel.
     */
    public function stream(Request $request, SupportTicket $ticket): StreamedResponse
    {
        $this->markIncomingAsDeliveredForStaff($ticket);
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
                        ->where('is_staff_reply', true)
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
                                    ? (($message->user?->name ?: 'Staff').' (support)')
                                    : ($message->user?->name ?: 'Client'),
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
                        ->where('is_staff_reply', true)
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

    public function markRead(SupportTicket $ticket): JsonResponse
    {
        $this->markIncomingAsReadForStaff($ticket);

        return response()->json(['ok' => true]);
    }

    private function markIncomingAsDeliveredForStaff(SupportTicket $ticket): void
    {
        SupportMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('is_staff_reply', false)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => now()]);
    }

    private function markIncomingAsReadForStaff(SupportTicket $ticket): void
    {
        SupportMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('is_staff_reply', false)
            ->whereNotNull('delivered_at')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
