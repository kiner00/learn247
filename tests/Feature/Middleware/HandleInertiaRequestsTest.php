<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class HandleInertiaRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(?User $user = null): Request
    {
        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        return $request;
    }

    public function test_share_includes_domain_community_when_attribute_is_set(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        $request = $this->makeRequest($user);
        $request->attributes->set('domain_community', $community);

        $middleware = new HandleInertiaRequests();
        $shared    = $middleware->share($request);

        $this->assertNotNull($shared['domain_community']);
        $this->assertEquals($community->id, $shared['domain_community']['id']);
        $this->assertEquals($community->slug, $shared['domain_community']['slug']);
    }

    public function test_share_returns_null_auth_user_and_zero_counters_when_guest(): void
    {
        $request    = $this->makeRequest(null);
        $middleware = new HandleInertiaRequests();
        $shared     = $middleware->share($request);

        $this->assertNull($shared['auth']['user']);
        $this->assertEquals([], $shared['auth']['communities']);
        $this->assertEquals(0, $shared['unread_messages']);
        $this->assertEquals(0, $shared['unread_dms']);
        $this->assertEquals(0, $shared['unread_notifications']);
        $this->assertNull($shared['domain_community']);
    }

    public function test_share_includes_auth_user_data_and_communities(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id, 'price' => 10]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $request    = $this->makeRequest($user);
        $middleware = new HandleInertiaRequests();
        $shared     = $middleware->share($request);

        $this->assertEquals($user->id, $shared['auth']['user']['id']);
        $this->assertEquals($user->email, $shared['auth']['user']['email']);
        $this->assertTrue($shared['auth']['user']['is_creator']);
        $this->assertArrayHasKey('kyc_status', $shared['auth']['user']);
        $this->assertEquals('light', $shared['auth']['user']['theme']);
        $this->assertCount(1, $shared['auth']['communities']);
        $this->assertEquals($community->id, $shared['auth']['communities'][0]['id']);
    }

    public function test_share_counts_unread_messages_dms_and_notifications(): void
    {
        $user   = User::factory()->create();
        $other  = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id'          => $community->id,
            'user_id'               => $user->id,
            'messages_last_read_at' => null,
        ]);
        Message::create([
            'community_id' => $community->id,
            'user_id'      => $other->id,
            'content'      => 'Hi',
        ]);

        DirectMessage::create([
            'sender_id'   => $other->id,
            'receiver_id' => $user->id,
            'content'     => 'Hello',
            'read_at'     => null,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'type'    => 'test',
            'data'    => ['title' => 'Test'],
            'read_at' => null,
        ]);

        $request    = $this->makeRequest($user);
        $middleware = new HandleInertiaRequests();
        $shared     = $middleware->share($request);

        $this->assertEquals(1, $shared['unread_messages']);
        $this->assertEquals(1, $shared['unread_dms']);
        $this->assertEquals(1, $shared['unread_notifications']);
    }

    public function test_share_flash_includes_session_values(): void
    {
        $user    = User::factory()->create();
        $request = $this->makeRequest($user);
        $request->session()->put('success', 'Done');
        $request->session()->put('error', 'Oops');
        $request->session()->put('show_ai_greeting', true);

        $middleware = new HandleInertiaRequests();
        $shared     = $middleware->share($request);

        $this->assertEquals('Done', $shared['flash']['success']);
        $this->assertEquals('Oops', $shared['flash']['error']);
        $this->assertTrue($shared['flash']['show_ai_greeting']);
    }

    public function test_version_returns_parent_version(): void
    {
        $request    = $this->makeRequest();
        $middleware = new HandleInertiaRequests();

        // Just assert it executes and returns either null or string without error
        $version = $middleware->version($request);
        $this->assertTrue($version === null || is_string($version));
    }

    public function test_url_resolver_returns_null_when_no_domain_community(): void
    {
        $middleware = new HandleInertiaRequests();
        $this->assertNull($middleware->urlResolver());
    }

    public function test_url_resolver_strips_prefix_when_domain_community_present(): void
    {
        $community = Community::factory()->create(['slug' => 'my-class']);

        // Bind a request into the container that has the attribute set
        $request = Request::create('/communities/my-class/classroom', 'GET');
        $request->attributes->set('domain_community', $community);
        $this->app->instance('request', $request);

        $middleware = new HandleInertiaRequests();
        $resolver   = $middleware->urlResolver();

        $this->assertIsCallable($resolver);
        $this->assertEquals('/classroom', $resolver());
    }

    public function test_url_resolver_returns_root_when_uri_equals_prefix(): void
    {
        $community = Community::factory()->create(['slug' => 'rooted']);

        $request = Request::create('/communities/rooted', 'GET');
        $request->attributes->set('domain_community', $community);
        $this->app->instance('request', $request);

        $middleware = new HandleInertiaRequests();
        $resolver   = $middleware->urlResolver();

        $this->assertEquals('/', $resolver());
    }

    public function test_url_resolver_returns_uri_as_is_when_prefix_does_not_match(): void
    {
        $community = Community::factory()->create(['slug' => 'mismatch']);

        $request = Request::create('/other/path', 'GET');
        $request->attributes->set('domain_community', $community);
        $this->app->instance('request', $request);

        $middleware = new HandleInertiaRequests();
        $resolver   = $middleware->urlResolver();

        $this->assertEquals('/other/path', $resolver());
    }
}
