<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
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
        });

        return redirect()
            ->route('support.tickets')
            ->with('status', 'Votre demande a été envoyée au support.');
    }

    public function show(SupportTicket $ticket)
    {
        $this->authorizeTicket($ticket);

        $ticket->load(['messages.user']);

        return view('support.show', compact('ticket'));
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

        return back()->with('status', 'Message ajouté.');
    }

    private function authorizeTicket(SupportTicket $ticket): void
    {
        if ((int) $ticket->user_id !== (int) Auth::id()) {
            abort(403);
        }
    }
}
