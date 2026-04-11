<?php

namespace App\Http\Controllers\Web;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\ReplyToTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTicketRequest;
use App\Http\Requests\ReplyTicketRequest;
use App\Mail\TicketStatusChangedMail;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    public function reopen(Ticket $ticket): RedirectResponse
    {
        if ($ticket->user_id !== auth()->id()) {
            abort(403);
        }

        if ($ticket->status !== 'resolved') {
            return back()->with('error', 'Only resolved tickets can be reopened.');
        }

        $ticket->update(['status' => 'open']);

        return back()->with('success', 'Ticket reopened.');
    }

    public function close(Ticket $ticket): RedirectResponse
    {
        if ($ticket->user_id !== auth()->id()) {
            abort(403);
        }

        if ($ticket->status !== 'resolved') {
            return back()->with('error', 'Only resolved tickets can be closed.');
        }

        $ticket->update(['status' => 'closed']);

        return back()->with('success', 'Ticket closed. Thank you for confirming!');
    }

    // ─── Admin methods ──────────────────────────────────────────────────────────

    public function adminIndex(): Response
    {
        $status = request('status');

        $query = Ticket::with('user:id,name,email,avatar')
            ->withCount('replies');

        if ($status && in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
            $query->where('status', $status);
        }

        $tickets = $query->latest()->paginate(20)->withQueryString();

        $counts = Ticket::selectRaw("
            COUNT(*) as all_count,
            SUM(status = 'open') as open_count,
            SUM(status = 'in_progress') as in_progress_count,
            SUM(status = 'resolved') as resolved_count,
            SUM(status = 'closed') as closed_count
        ")->first();

        return Inertia::render('Admin/Tickets', [
            'tickets' => $tickets,
            'counts'  => [
                'all'         => (int) $counts->all_count,
                'open'        => (int) $counts->open_count,
                'in_progress' => (int) $counts->in_progress_count,
                'resolved'    => (int) $counts->resolved_count,
                'closed'      => (int) $counts->closed_count,
            ],
            'currentStatus' => $status,
        ]);
    }

    public function adminUpdateStatus(Ticket $ticket): RedirectResponse
    {
        $status = request()->validate(['status' => 'required|in:open,in_progress,resolved,closed'])['status'];

        $oldStatus = $ticket->status;
        $ticket->update(['status' => $status]);

        if ($oldStatus !== $status && $ticket->user?->email) {
            Mail::to($ticket->user->email)->queue(new TicketStatusChangedMail($ticket, $oldStatus, $status));
        }

        return back()->with('success', 'Ticket status updated.');
    }
}
