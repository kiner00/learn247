<?php

namespace App\Http\Controllers\Web;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\ReplyToTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTicketRequest;
use App\Http\Requests\ReplyTicketRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(): Response
    {
        $tickets = Ticket::where('user_id', auth()->id())
            ->withCount('replies')
            ->latest()
            ->paginate(15);

        return Inertia::render('Support/Index', [
            'tickets' => $tickets,
        ]);
    }

    public function store(CreateTicketRequest $request, CreateTicket $action): RedirectResponse
    {
        try {
            $action->execute($request->user(), $request->validated());

            return back()->with('success', 'Ticket submitted successfully.');
        } catch (\Throwable $e) {
            Log::error('TicketController@store failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to submit ticket.');
        }
    }

    public function show(Ticket $ticket): Response|RedirectResponse
    {
        if ($ticket->user_id !== auth()->id() && ! auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $ticket->load([
            'attachments',
            'replies' => fn ($q) => $q->with('user:id,name,avatar')->oldest(),
            'user:id,name,avatar',
        ]);

        return Inertia::render('Support/Show', [
            'ticket'  => $ticket,
            'isAdmin' => request()->routeIs('admin.*'),
        ]);
    }

    public function reply(ReplyTicketRequest $request, Ticket $ticket, ReplyToTicket $action): RedirectResponse
    {
        if ($ticket->user_id !== auth()->id() && ! auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        try {
            $action->execute($request->user(), $ticket, $request->validated());

            return back()->with('success', 'Reply sent.');
        } catch (\Throwable $e) {
            Log::error('TicketController@reply failed', ['error' => $e->getMessage(), 'ticket_id' => $ticket->id]);
            return back()->with('error', 'Failed to send reply.');
        }
    }

    // ─── Admin methods ──────────────────────────────────────────────────────────

    public function adminIndex(): Response
    {
        $tickets = Ticket::with('user:id,name,email,avatar')
            ->withCount('replies')
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Tickets', [
            'tickets' => $tickets,
        ]);
    }

    public function adminUpdateStatus(Ticket $ticket): RedirectResponse
    {
        $status = request()->validate(['status' => 'required|in:open,in_progress,resolved,closed'])['status'];

        $ticket->update(['status' => $status]);

        return back()->with('success', 'Ticket status updated.');
    }
}
