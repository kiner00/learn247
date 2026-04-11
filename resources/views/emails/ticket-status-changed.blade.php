<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 40px 0; }
        .card { background: white; max-width: 560px; margin: 0 auto; border-radius: 12px; padding: 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .badge { display: inline-block; background: #fef3c7; color: #b45309; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 99px; margin-bottom: 16px; letter-spacing: .5px; }
        .status { display: inline-block; padding: 4px 12px; border-radius: 99px; font-size: 13px; font-weight: 600; }
        .status-open { background: #dcfce7; color: #15803d; }
        .status-in_progress { background: #fef3c7; color: #b45309; }
        .status-resolved { background: #dbeafe; color: #1d4ed8; }
        .status-closed { background: #f3f4f6; color: #6b7280; }
        .btn { display: inline-block; background: #f59e0b; color: white; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">SUPPORT TICKET #{{ $ticket->id }}</div>
        <h2 style="margin-top:0; margin-bottom: 8px;">{{ $ticket->subject }}</h2>

        <p style="font-size: 15px; line-height: 1.7;">
            Your ticket status has been updated from
            <span class="status status-{{ $oldStatus }}">{{ str_replace('_', ' ', ucfirst($oldStatus)) }}</span>
            to
            <span class="status status-{{ $newStatus }}">{{ str_replace('_', ' ', ucfirst($newStatus)) }}</span>.
        </p>

        @if($newStatus === 'resolved')
            <p style="font-size: 14px; color: #4b5563; line-height: 1.6;">
                If this resolves your issue, please close the ticket. If you still need help, you can reopen it from the ticket page.
            </p>
        @endif

        <a href="{{ config('app.url') }}/support/{{ $ticket->id }}" class="btn">View Ticket →</a>

        <div class="footer">
            You received this email because you submitted a support ticket.
        </div>
    </div>
</body>
</html>
