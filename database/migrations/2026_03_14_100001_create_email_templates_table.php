<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->longText('html_body');
            $table->json('variables')->nullable();
            $table->timestamps();
        });

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }

    private function seedDefaults(): void
    {
        $appUrl = config('app.url');
        $now = now();

        DB::table('email_templates')->insert([
            [
                'key' => 'welcome',
                'name' => 'Welcome / Temp Password',
                'subject' => "You're in! Here's your login for {{community_name}}",
                'html_body' => '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
body{font-family:sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:40px 0}
.card{background:white;max-width:520px;margin:0 auto;border-radius:12px;padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.badge{display:inline-block;background:#ecfdf5;color:#059669;font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px;margin-bottom:16px;letter-spacing:.5px}
.password-box{background:#f3f4f6;border:1px dashed #d1d5db;border-radius:8px;padding:16px 24px;text-align:center;margin:20px 0;font-size:22px;font-weight:700;letter-spacing:3px;color:#111827;font-family:monospace}
.btn{display:inline-block;background:#4F46E5;color:white;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:600;margin:20px 0}
.footer{font-size:13px;color:#6b7280;margin-top:24px;border-top:1px solid #f3f4f6;padding-top:16px}
</style>
</head>
<body>
<div class="card">
<div class="badge">PAYMENT CONFIRMED</div>
<h2 style="margin-top:0">Welcome to {{community_name}}!</h2>
<p>Hi {{user_name}},</p>
<p>Your payment was successful. Use the temporary password below to log in:</p>
<div class="password-box">{{temp_password}}</div>
<p style="font-size:13px;color:#6b7280;margin-top:-8px">Login email: <strong>{{user_email}}</strong></p>
<a href="{{login_url}}" class="btn">Log In Now</a>
<p>Once logged in, you\'ll be prompted to set a permanent password.</p>
<div class="footer"><p>For security, please change your password as soon as you log in. If you didn\'t make this purchase, please contact support immediately.</p></div>
</div>
</body>
</html>',
                'variables' => json_encode([
                    'community_name' => 'Name of the community',
                    'user_name'      => 'Member\'s full name',
                    'user_email'     => 'Member\'s login email',
                    'temp_password'  => 'Temporary password',
                    'login_url'      => 'URL to the login page',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'password-reminder',
                'name' => 'Password Change Reminder',
                'subject' => 'Reminder: Please change your temporary password',
                'html_body' => '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
body{font-family:sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:40px 0}
.card{background:white;max-width:520px;margin:0 auto;border-radius:12px;padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.badge{display:inline-block;background:#fef3c7;color:#d97706;font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px;margin-bottom:16px;letter-spacing:.5px}
.btn{display:inline-block;background:#4F46E5;color:white;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:600;margin:20px 0}
.footer{font-size:13px;color:#6b7280;margin-top:24px;border-top:1px solid #f3f4f6;padding-top:16px}
</style>
</head>
<body>
<div class="card">
<div class="badge">SECURITY REMINDER</div>
<h2 style="margin-top:0">Change your temporary password</h2>
<p>Hi {{user_name}},</p>
<p>You\'re still using the temporary password we sent you when you joined. For your security, please log in and set a permanent password.</p>
<a href="{{login_url}}" class="btn">Log In &amp; Change Password</a>
<div class="footer"><p>If you\'ve already changed your password, you can ignore this email.</p></div>
</div>
</body>
</html>',
                'variables' => json_encode([
                    'user_name'  => 'Member\'s full name',
                    'login_url'  => 'URL to the login page',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'subscription-renewal',
                'name' => 'Subscription Renewal (5 days)',
                'subject' => 'Your {{community_name}} subscription expires in 5 days',
                'html_body' => '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
body{font-family:sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:40px 0}
.card{background:white;max-width:520px;margin:0 auto;border-radius:12px;padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.btn{display:inline-block;background:#4F46E5;color:white;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:600;margin:24px 0}
.footer{font-size:13px;color:#6b7280;margin-top:24px}
</style>
</head>
<body>
<div class="card">
<h2 style="margin-top:0">Your subscription is expiring soon</h2>
<p>Hi {{user_name}},</p>
<p>Your subscription to <strong>{{community_name}}</strong> expires in 5 days on <strong>{{expiry_date}}</strong>.</p>
<p>Renew early to keep your access uninterrupted:</p>
<a href="{{renewal_url}}" class="btn">Renew Subscription</a>
<p class="footer">If you no longer wish to stay subscribed, simply ignore this email and your access will end on the expiry date.</p>
</div>
</body>
</html>',
                'variables' => json_encode([
                    'user_name'      => 'Member\'s full name',
                    'community_name' => 'Name of the community',
                    'expiry_date'    => 'Formatted expiry date (e.g. March 20, 2026)',
                    'renewal_url'    => 'URL to renew the subscription',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'subscription-renewal-urgent',
                'name' => 'Subscription Renewal (expires tomorrow)',
                'subject' => 'Last chance: {{community_name}} subscription expires tomorrow',
                'html_body' => '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
body{font-family:sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:40px 0}
.card{background:white;max-width:520px;margin:0 auto;border-radius:12px;padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.badge{display:inline-block;background:#fef2f2;color:#dc2626;font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px;margin-bottom:16px;letter-spacing:.5px}
.btn{display:inline-block;background:#dc2626;color:white;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:600;margin:24px 0}
.footer{font-size:13px;color:#6b7280;margin-top:24px}
</style>
</head>
<body>
<div class="card">
<div class="badge">EXPIRES TOMORROW</div>
<h2 style="margin-top:0">Last chance to renew</h2>
<p>Hi {{user_name}},</p>
<p>Your subscription to <strong>{{community_name}}</strong> expires <strong>tomorrow, {{expiry_date}}</strong>. Renew now to avoid losing access.</p>
<a href="{{renewal_url}}" class="btn">Renew Subscription</a>
<p class="footer">If you no longer wish to stay subscribed, simply ignore this email and your access will end on the expiry date.</p>
</div>
</body>
</html>',
                'variables' => json_encode([
                    'user_name'      => 'Member\'s full name',
                    'community_name' => 'Name of the community',
                    'expiry_date'    => 'Formatted expiry date (e.g. March 20, 2026)',
                    'renewal_url'    => 'URL to renew the subscription',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'community-invite',
                'name' => 'Community Invitation',
                'subject' => "You're invited to join {{community_name}}",
                'html_body' => '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
body{font-family:sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:40px 0}
.card{background:white;max-width:560px;margin:0 auto;border-radius:12px;padding:40px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.badge{display:inline-block;background:#eff6ff;color:#3b82f6;font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px;margin-bottom:16px;letter-spacing:.5px}
.btn{display:inline-block;background:#4F46E5;color:white;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:600;margin:24px 0}
.footer{font-size:12px;color:#9ca3af;margin-top:24px;border-top:1px solid #f3f4f6;padding-top:16px}
</style>
</head>
<body>
<div class="card">
<div class="badge">INVITATION · {{community_name_upper}}</div>
<h2 style="margin-top:0;margin-bottom:8px">You\'re invited! 🎉</h2>
<p>You\'ve been personally invited to join <strong>{{community_name}}</strong> on Curzzo.</p>
<p style="color:#6b7280">{{community_description}}</p>
<p>Click the button below to accept your invitation and get instant access.</p>
<a href="{{invite_url}}" class="btn">Accept Invitation →</a>
<div class="footer">This invite was sent to <strong>{{invite_email}}</strong> and expires in 7 days.<br>If you did not expect this email, you can safely ignore it.</div>
</div>
</body>
</html>',
                'variables' => json_encode([
                    'community_name'       => 'Name of the community',
                    'community_name_upper' => 'Community name in uppercase (for badge)',
                    'community_description'=> 'Community description',
                    'invite_url'           => 'Invitation acceptance URL',
                    'invite_email'         => 'Email address the invite was sent to',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
};
