<?php

namespace Tests\Feature\Providers;

use App\Models\User;
use App\Providers\HorizonServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Horizon\Horizon;
use Tests\TestCase;

class HorizonServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    private function invokeAuthCallback(?User $user): bool
    {
        // Boot a fresh provider so it registers the Horizon::auth callback
        (new HorizonServiceProvider($this->app))->boot();

        $request = Request::create('/horizon', 'GET');
        $request->setUserResolver(fn () => $user);

        return (bool) Horizon::check($request);
    }

    public function test_super_admin_can_access_horizon(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->assertTrue($this->invokeAuthCallback($admin));
    }

    public function test_regular_user_cannot_access_horizon(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $this->assertFalse($this->invokeAuthCallback($user));
    }

    public function test_guest_cannot_access_horizon(): void
    {
        $this->assertFalse($this->invokeAuthCallback(null));
    }
}
