<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('email_templates')->insertOrIgnore([
            [
                'key' => 'affiliate-cha-ching',
                'name' => 'Affiliate Sale Notification (Cha-ching)',
                'subject' => '💰 Cha-ching! You made a sale from {{community_name}}',
                'html_body' => $this->affiliateHtml(),
                'variables' => json_encode([
                    'affiliate_name' => 'Name of the affiliate',
                    'community_name' => 'Name of the community',
                    'sale_amount' => 'Sale amount (e.g. 999.00)',
                    'commission_amount' => 'Commission earned (e.g. 199.80)',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'creator-cha-ching',
                'name' => 'Creator Sale Notification (Cha-ching)',
                'subject' => '💰 Cha-ching! New sale in {{community_name}}',
                'html_body' => $this->creatorHtml(),
                'variables' => json_encode([
                    'creator_name' => 'Name of the community creator',
                    'community_name' => 'Name of the community',
                    'sale_amount' => 'Sale amount (e.g. 999.00)',
                    'referred_by' => 'Name of the affiliate who referred the buyer (blank if direct)',
                    'dashboard_url' => 'Link to the creator dashboard',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->whereIn('key', ['affiliate-cha-ching', 'creator-cha-ching'])->delete();
    }

    private function affiliateHtml(): string
    {
        return <<<'HTML'
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
    <p>Hi {{affiliate_name}},</p>
    <p>Congratulations! You made a sale of <strong>₱{{sale_amount}}</strong> from <strong>{{community_name}}</strong>.</p>
    <div class="amount-box">
      <div class="label">Your Commission</div>
      <div class="value">₱{{commission_amount}}</div>
    </div>
    <p>Keep sharing your referral link to earn more commissions!</p>
    <div class="footer"><p>Powered by Curzzo</p></div>
  </div>
</body>
</html>
HTML;
    }

    private function creatorHtml(): string
    {
        return <<<'HTML'
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
  .ref-pill { display: inline-block; background: #eff6ff; color: #1d4ed8; font-size: 13px; font-weight: 600; padding: 4px 12px; border-radius: 99px; margin-top: 8px; }
  .footer { font-size: 13px; color: #6b7280; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
</style>
</head>
<body>
  <div class="card">
    <div class="badge">💰 CHA-CHING!</div>
    <h2 style="margin-top:0">New sale in {{community_name}}!</h2>
    <p>Hi {{creator_name}},</p>
    <p>Congratulations! You made a sale of <strong>₱{{sale_amount}}</strong> from <strong>{{community_name}}</strong>{{referred_by}}.</p>
    <div class="amount-box">
      <div class="label">Sale Amount</div>
      <div class="value">₱{{sale_amount}}</div>
    </div>
    <p>Check your <a href="{{dashboard_url}}">Creator Dashboard</a> for full earnings details.</p>
    <div class="footer"><p>Powered by Curzzo</p></div>
  </div>
</body>
</html>
HTML;
    }
};
