<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 40px 0; }
        .card { background: white; max-width: 520px; margin: 0 auto; border-radius: 12px; padding: 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .badge-approved { display: inline-block; background: #d1fae5; color: #059669; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 99px; margin-bottom: 16px; letter-spacing: .5px; }
        .badge-rejected { display: inline-block; background: #fee2e2; color: #dc2626; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 99px; margin-bottom: 16px; letter-spacing: .5px; }
        .reason { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin: 16px 0; color: #991b1b; font-size: 14px; }
        .btn { display: inline-block; background: #4F46E5; color: white; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .footer { font-size: 13px; color: #6b7280; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        @if($approved)
            <div class="badge-approved">VERIFIED</div>
            <h2 style="margin-top:0">Identity verification approved!</h2>

            <p>Hi {{ $user->name }},</p>

            <p>
                Great news! Your identity has been successfully verified.
                Your communities are now eligible to appear on the directory.
            </p>

            <a href="{{ config('app.url') }}/account/settings" class="btn">Go to Settings</a>
        @else
            <div class="badge-rejected">ACTION NEEDED</div>
            <h2 style="margin-top:0">Identity verification was not approved</h2>

            <p>Hi {{ $user->name }},</p>

            <p>Unfortunately, we were unable to verify your identity at this time.</p>

            @if($reason)
                <div class="reason">
                    <strong>Reason:</strong> {{ $reason }}
                </div>
            @endif

            <p>Please re-submit your documents with clearer photos. Make sure:</p>
            <ul>
                <li>Your government ID is clearly readable</li>
                <li>Your selfie shows both your face and ID clearly</li>
                <li>Images are well-lit and not blurry</li>
            </ul>

            <a href="{{ config('app.url') }}/account/settings" class="btn">Re-submit Documents</a>
        @endif

        <div class="footer">
            <p>If you have any questions, please contact our support team.</p>
        </div>
    </div>
</body>
</html>
