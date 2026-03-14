<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templates = $this->templates();
        foreach ($templates as $key => $data) {
            DB::table('email_templates')
                ->where('key', $key)
                ->update(['html_body' => $data]);
        }
    }

    public function down(): void
    {
        // No rollback — original templates were class-based which broke Gmail
    }

    private function templates(): array
    {
        return [
            'welcome' => '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="font-family:Arial,sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:40px 0">
<div style="background:white;max-width:520px;margin:0 auto;border-radius:12px;padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
  <div style="display:inline-block;background:#ecfdf5;color:#059669;font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px;margin-bottom:16px;letter-spacing:.5px">PAYMENT CONFIRMED</div>
  <h2 style="margin-top:0;color:#111827">Welcome to {{community_name}}!</h2>
  <p>Hi {{user_name}},</p>
  <p>Your payment was successful. Use the temporary password below to log in:</p>
  <div style="background:#f3f4f6;border:1px dashed #d1d5db;border-radius:8px;padding:16px 24px;text-align:center;margin:20px 0;font-size:22px;font-weight:700;letter-spacing:3px;color:#111827;font-family:monospace">{{temp_password}}</div>
  <p style="font-size:13px;color:#6b7280;margin-top:-8px">Login email: <strong>{{user_email}}</strong></p>
  <a href="{{login_url}}" style="display:inline-block;background:#4F46E5;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:600;margin:20px 0;font-size:15px">Log In Now</a>
  <p>Once logged in, you\'ll be prompted to set a permanent password.</p>
  <div style="font-size:13px;color:#6b7280;margin-top:24px;border-top:1px solid #f3f4f6;padding-top:16px">
    <p>For security, please change your password as soon as you log in. If you didn\'t make this purchase, please contact support immediately.</p>
  </div>
</div>
</body>
</html>',

            'password-reminder' => '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="font-family:Arial,sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:40px 0">
<div style="background:white;max-width:520px;margin:0 auto;border-radius:12px;padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
  <div style="display:inline-block;background:#fef3c7;color:#d97706;font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px;margin-bottom:16px;letter-spacing:.5px">SECURITY REMINDER</div>
  <h2 style="margin-top:0;color:#111827">Change your temporary password</h2>
  <p>Hi {{user_name}},</p>
  <p>You\'re still using the temporary password we sent you when you joined. For your security, please log in and set a permanent password.</p>
  <a href="{{login_url}}" style="display:inline-block;background:#4F46E5;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:600;margin:20px 0;font-size:15px">Log In &amp; Change Password</a>
  <div style="font-size:13px;color:#6b7280;margin-top:24px;border-top:1px solid #f3f4f6;padding-top:16px">
    <p>If you\'ve already changed your password, you can ignore this email.</p>
  </div>
</div>
</body>
</html>',

            'subscription-renewal' => '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="font-family:Arial,sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:40px 0">
<div style="background:white;max-width:520px;margin:0 auto;border-radius:12px;padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
  <h2 style="margin-top:0;color:#111827">Your subscription is expiring soon</h2>
  <p>Hi {{user_name}},</p>
  <p>Your subscription to <strong>{{community_name}}</strong> expires in 5 days on <strong>{{expiry_date}}</strong>.</p>
  <p>Renew early to keep your access uninterrupted:</p>
  <a href="{{renewal_url}}" style="display:inline-block;background:#4F46E5;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:600;margin:24px 0;font-size:15px">Renew Subscription</a>
  <p style="font-size:13px;color:#6b7280;margin-top:24px">If you no longer wish to stay subscribed, simply ignore this email and your access will end on the expiry date.</p>
</div>
</body>
</html>',

            'subscription-renewal-urgent' => '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="font-family:Arial,sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:40px 0">
<div style="background:white;max-width:520px;margin:0 auto;border-radius:12px;padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
  <div style="display:inline-block;background:#fef2f2;color:#dc2626;font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px;margin-bottom:16px;letter-spacing:.5px">EXPIRES TOMORROW</div>
  <h2 style="margin-top:0;color:#111827">Last chance to renew</h2>
  <p>Hi {{user_name}},</p>
  <p>Your subscription to <strong>{{community_name}}</strong> expires <strong>tomorrow, {{expiry_date}}</strong>. Renew now to avoid losing access.</p>
  <a href="{{renewal_url}}" style="display:inline-block;background:#dc2626;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:600;margin:24px 0;font-size:15px">Renew Subscription</a>
  <p style="font-size:13px;color:#6b7280;margin-top:24px">If you no longer wish to stay subscribed, simply ignore this email and your access will end on the expiry date.</p>
</div>
</body>
</html>',

            'community-invite' => '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="font-family:Arial,sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:40px 0">
<div style="background:white;max-width:560px;margin:0 auto;border-radius:12px;padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
  <div style="display:inline-block;background:#eff6ff;color:#3b82f6;font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px;margin-bottom:16px;letter-spacing:.5px">INVITATION &middot; {{community_name_upper}}</div>
  <h2 style="margin-top:0;margin-bottom:8px;color:#111827">You\'re invited! &#127881;</h2>
  <p>You\'ve been personally invited to join <strong>{{community_name}}</strong> on Curzzo.</p>
  <p style="color:#6b7280">{{community_description}}</p>
  <p>Click the button below to accept your invitation and get instant access.</p>
  <a href="{{invite_url}}" style="display:inline-block;background:#4F46E5;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:600;margin:24px 0;font-size:15px">Accept Invitation &rarr;</a>
  <div style="font-size:12px;color:#9ca3af;margin-top:24px;border-top:1px solid #f3f4f6;padding-top:16px">
    This invite was sent to <strong>{{invite_email}}</strong> and expires in 7 days.<br>
    If you did not expect this email, you can safely ignore it.
  </div>
</div>
</body>
</html>',
        ];
    }
};
