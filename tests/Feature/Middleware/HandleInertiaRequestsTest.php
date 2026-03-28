<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class HandleInertiaRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_includes_domain_community_when_attribute_is_set(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('domain_community', $community);

        $middleware = new HandleInertiaRequests();
        $shared    = $middleware->share($request);

        $this->assertNotNull($shared['domain_community']);
        $this->assertEquals($community->id, $shared['domain_community']['id']);
        $this->assertEquals($community->slug, $shared['domain_community']['slug']);
    }
}
