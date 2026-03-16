<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 40px 0; }
        .card { background: white; max-width: 520px; margin: 0 auto; border-radius: 12px; padding: 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .badge { display: inline-block; background: #fef9c3; color: #92400e; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 99px; margin-bottom: 16px; letter-spacing: .5px; }
        .amount-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 20px 24px; text-align: center; margin: 24px 0; }
        .amount-box .label { font-size: 13px; color: #6b7280; margin-bottom: 4px; }
        .amount-box .value { font-size: 32px; font-weight: 900; color: #16a34a; }
        .footer { font-size: 13px; color: #6b7280; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">💰 CHA-CHING!</div>
        <h2 style="margin-top:0">You made a sale!</h2>

        <p>Hi {{ $affiliate->name }},</p>

        <p>Congratulations! You made a sale of <strong>₱{{ number_format($saleAmount, 2) }}</strong> from <strong>{{ $community->name }}</strong>.</p>

        <div class="amount-box">
            <div class="label">Your Commission</div>
            <div class="value">₱{{ number_format($commissionAmount, 2) }}</div>
        </div>

        <p>Keep sharing your referral link to earn more commissions!</p>

        <div class="footer">
            <p>Powered by Curzzo · <a href="{{ config('app.url') }}">curzzo.com</a></p>
        </div>
    </div>
</body>
</html>
