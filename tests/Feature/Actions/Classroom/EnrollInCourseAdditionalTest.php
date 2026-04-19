<?php

namespace Tests\Feature\Actions\Classroom;

use App\Actions\Classroom\EnrollInCourse;
use App\Models\Community;
use App\Models\Course;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class EnrollInCourseAdditionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_xendit_api_failure_is_rethrown(): void
    {
        $this->mock(XenditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createInvoice')
                ->once()
                ->andThrow(new \RuntimeException('Xendit timeout'));
        });

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Paid Course',
            'access_type' => Course::ACCESS_PAID_ONCE,
            'price' => 500,
            'position' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Xendit timeout');

        $action = app(EnrollInCourse::class);
        $action->execute($user, $community, $course, 'https://example.com/success');
    }

    public function test_monthly_label_includes_monthly_suffix(): void
    {
        $this->mock(XenditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createInvoice')
                ->withArgs(function (array $payload) {
                    return str_contains($payload['description'], '(Monthly)');
                })
                ->once()
                ->andReturn([
                    'id' => 'inv_monthly_label',
                    'invoice_url' => 'https://checkout.xendit.co/monthly_label',
                ]);
        });

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Monthly Course',
            'access_type' => Course::ACCESS_PAID_MONTHLY,
            'price' => 200,
            'position' => 1,
        ]);

        $action = app(EnrollInCourse::class);
        $action->execute($user, $community, $course, 'https://example.com/success');
    }

    public function test_default_community_currency_is_php(): void
    {
        $this->mock(XenditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createInvoice')
                ->withArgs(function (array $payload) {
                    return $payload['currency'] === 'PHP';
                })
                ->once()
                ->andReturn([
                    'id' => 'inv_default_curr',
                    'invoice_url' => 'https://checkout.xendit.co/default_curr',
                ]);
        });

        $user = User::factory()->create();
        // Community uses the default currency (PHP) from the DB column default
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Default Curr Course',
            'access_type' => Course::ACCESS_PAID_ONCE,
            'price' => 300,
            'position' => 1,
        ]);

        $action = app(EnrollInCourse::class);
        $action->execute($user, $community, $course, 'https://example.com/success');
    }
}
