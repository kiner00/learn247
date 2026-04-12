<?php

namespace Tests\Feature\Models;

use App\Models\Community;
use App\Models\EmailDailyStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailDailyStatTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_use_timestamps(): void
    {
        $stat = new EmailDailyStat();
        $this->assertFalse($stat->timestamps);
    }

    public function test_date_is_cast_to_date_instance(): void
    {
        $community = Community::factory()->create();

        $stat = EmailDailyStat::create([
            'community_id' => $community->id,
            'date'         => '2026-04-10',
            'sent'         => 10,
            'delivered'    => 9,
            'opened'       => 5,
            'clicked'      => 2,
            'bounced'      => 1,
            'complained'   => 0,
            'unsubscribed' => 0,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $stat->fresh()->date);
        $this->assertSame('2026-04-10', $stat->fresh()->date->toDateString());
    }

    public function test_belongs_to_community(): void
    {
        $community = Community::factory()->create();

        $stat = EmailDailyStat::create([
            'community_id' => $community->id,
            'date'         => '2026-04-10',
            'sent'         => 1,
            'delivered'    => 1,
            'opened'       => 0,
            'clicked'      => 0,
            'bounced'      => 0,
            'complained'   => 0,
            'unsubscribed' => 0,
        ]);

        $this->assertTrue($stat->community->is($community));
    }

    public function test_fillable_fields_are_mass_assignable(): void
    {
        $community = Community::factory()->create();

        $stat = EmailDailyStat::create([
            'community_id' => $community->id,
            'date'         => '2026-04-10',
            'sent'         => 100,
            'delivered'    => 95,
            'opened'       => 50,
            'clicked'      => 20,
            'bounced'      => 3,
            'complained'   => 1,
            'unsubscribed' => 2,
        ]);

        $fresh = $stat->fresh();
        $this->assertSame(100, $fresh->sent);
        $this->assertSame(95, $fresh->delivered);
        $this->assertSame(50, $fresh->opened);
        $this->assertSame(20, $fresh->clicked);
        $this->assertSame(3, $fresh->bounced);
        $this->assertSame(1, $fresh->complained);
        $this->assertSame(2, $fresh->unsubscribed);
    }
}
