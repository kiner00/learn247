<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\ResolveCustomDomain;
use App\Models\Community;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ResolveCustomDomainTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: build a Request with the given host & URI, run it through the middleware,
     * and return [community_slug|null, final_request_uri].
     */
    private function runMiddleware(string $url): array
    {
        $parts   = parse_url($url);
        $host    = $parts['host'] ?? 'localhost';
        $path    = $parts['path'] ?? '/';
        $query   = $parts['query'] ?? '';
        $uri     = $query ? "{$path}?{$query}" : $path;

        $request = Request::create($uri, 'GET');
        $request->headers->set('HOST', $host);
        $request->server->set('HTTP_HOST', $host);

        $communitySlug = null;
        $finalUri      = null;

        $middleware = new ResolveCustomDomain();
        $middleware->handle($request, function (Request $req) use (&$communitySlug, &$finalUri) {
            $communitySlug = $req->attributes->get('domain_community')?->slug;
            $finalUri      = $req->getRequestUri();
            return response()->json(['ok' => true]);
        });

        return [$communitySlug, $finalUri];
    }

    // ── same host (no rewriting) ─────────────────────────────────────────────

    public function test_same_host_passes_through_without_rewriting(): void
    {
        config(['app.url' => 'https://curzzo.com']);

        [$community, $uri] = $this->runMiddleware('https://curzzo.com/test-domain');

        $this->assertNull($community);
        $this->assertEquals('/test-domain', $uri);
    }

    // ── subdomain resolves to community ──────────────────────────────────────

    public function test_subdomain_resolves_community_and_rewrites_uri(): void
    {
        config(['app.url' => 'https://curzzo.com']);

        Community::factory()->create(['subdomain' => 'testclass', 'slug' => 'testclass-slug']);

        [$community, $uri] = $this->runMiddleware('https://testclass.curzzo.com/classroom');

        $this->assertEquals('testclass-slug', $community);
        $this->assertStringStartsWith('/communities/testclass-slug', $uri);
    }

    public function test_subdomain_root_path_rewrites_correctly(): void
    {
        config(['app.url' => 'https://curzzo.com']);

        Community::factory()->create(['subdomain' => 'roottest', 'slug' => 'roottest-slug']);

        [$community, $uri] = $this->runMiddleware('https://roottest.curzzo.com/');

        $this->assertEquals('roottest-slug', $community);
        $this->assertEquals('/communities/roottest-slug', $uri);
    }

    // ── subdomain not found passes through ───────────────────────────────────

    public function test_unknown_subdomain_passes_through(): void
    {
        config(['app.url' => 'https://curzzo.com']);

        [$community, $uri] = $this->runMiddleware('https://nonexistent.curzzo.com/test-domain');

        $this->assertNull($community);
    }

    // ── nested subdomain (multi-level) is ignored ────────────────────────────

    public function test_nested_subdomain_is_ignored(): void
    {
        config(['app.url' => 'https://curzzo.com']);

        Community::factory()->create(['subdomain' => 'deep', 'slug' => 'deep-slug']);

        // deep.nested.curzzo.com should NOT match because "deep.nested" contains a dot
        [$community, $uri] = $this->runMiddleware('https://deep.nested.curzzo.com/test-domain');

        $this->assertNull($community);
    }

    // ── custom domain resolves to community ──────────────────────────────────

    public function test_custom_domain_resolves_community_and_rewrites_uri(): void
    {
        config(['app.url' => 'https://curzzo.com']);

        Community::factory()->create(['custom_domain' => 'myclassroom.com', 'slug' => 'custom-slug']);

        [$community, $uri] = $this->runMiddleware('https://myclassroom.com/classroom');

        $this->assertEquals('custom-slug', $community);
        $this->assertStringStartsWith('/communities/custom-slug', $uri);
    }

    // ── unknown custom domain passes through ─────────────────────────────────

    public function test_unknown_custom_domain_passes_through(): void
    {
        config(['app.url' => 'https://curzzo.com']);

        [$community, $uri] = $this->runMiddleware('https://unknowndomain.com/test-domain');

        $this->assertNull($community);
    }

    // ── URI already prefixed skips rewriting ─────────────────────────────────

    public function test_already_prefixed_uri_is_not_rewritten(): void
    {
        config(['app.url' => 'https://curzzo.com']);

        Community::factory()->create(['subdomain' => 'prefixed', 'slug' => 'prefixed-slug']);

        [$community, $uri] = $this->runMiddleware('https://prefixed.curzzo.com/communities/prefixed-slug');

        $this->assertEquals('prefixed-slug', $community);
        // URI should remain the same (not double-prefixed)
        $this->assertEquals('/communities/prefixed-slug', $uri);
    }

    // ── query string is preserved during rewriting ───────────────────────────

    public function test_query_string_is_preserved_during_rewriting(): void
    {
        config(['app.url' => 'https://curzzo.com']);

        Community::factory()->create(['subdomain' => 'qstest', 'slug' => 'qs-slug']);

        [$community, $uri] = $this->runMiddleware('https://qstest.curzzo.com/classroom?tab=courses&page=2');

        $this->assertEquals('qs-slug', $community);
        $this->assertStringContainsString('tab=courses', $uri);
        $this->assertStringContainsString('page=2', $uri);
    }

    // ── empty bare host (edge case) passes through ───────────────────────────

    public function test_empty_app_url_passes_through(): void
    {
        config(['app.url' => '']);

        [$community, $uri] = $this->runMiddleware('https://anything.com/test-domain');

        $this->assertNull($community);
    }

    // ── app URL with port ────────────────────────────────────────────────────

    public function test_app_url_with_port_strips_port_for_host_comparison(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        // Same host (localhost) should pass through
        [$community, $uri] = $this->runMiddleware('http://localhost/test-domain');

        $this->assertNull($community);
    }

    // ── auth paths skip the rewrite ──────────────────────────────────────────

    public function test_auth_paths_are_not_rewritten_on_custom_domain(): void
    {
        config(['app.url' => 'https://curzzo.com']);

        Community::factory()->create(['subdomain' => 'authtest', 'slug' => 'auth-slug']);

        [$community, $uri] = $this->runMiddleware('https://authtest.curzzo.com/login');

        // Community attribute is still set so Inertia sharing works
        $this->assertEquals('auth-slug', $community);
        // But URI is NOT rewritten to /communities/{slug}/login
        $this->assertEquals('/login', $uri);
    }

    public function test_nested_auth_path_is_not_rewritten(): void
    {
        config(['app.url' => 'https://curzzo.com']);

        Community::factory()->create(['subdomain' => 'authnest', 'slug' => 'authnest-slug']);

        [$community, $uri] = $this->runMiddleware('https://authnest.curzzo.com/reset-password/token123');

        $this->assertEquals('authnest-slug', $community);
        $this->assertEquals('/reset-password/token123', $uri);
    }

    public function test_certificates_path_is_not_rewritten(): void
    {
        config(['app.url' => 'https://curzzo.com']);

        Community::factory()->create(['subdomain' => 'certs', 'slug' => 'certs-slug']);

        [$community, $uri] = $this->runMiddleware('https://certs.curzzo.com/certificates/abc');

        $this->assertEquals('certs-slug', $community);
        $this->assertEquals('/certificates/abc', $uri);
    }

}
