<?php

namespace Tests\Feature\Requests;

use App\Http\Requests\CreateCommunityRequest;
use App\Models\Community;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CreateCommunityRequestTest extends TestCase
{
    use RefreshDatabase;

    private function prepareRequest(array $data): CreateCommunityRequest
    {
        $req = CreateCommunityRequest::create('/communities', 'POST', $data);
        // Trigger prepareForValidation via reflection
        $method = new \ReflectionMethod($req, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($req);

        return $req;
    }

    public function test_slug_is_auto_generated_from_name_when_missing(): void
    {
        $req = $this->prepareRequest(['name' => 'My Cool Community']);

        $this->assertSame('my-cool-community', $req->input('slug'));
    }

    public function test_slug_generation_appends_suffix_when_slug_is_taken(): void
    {
        Community::factory()->create(['slug' => 'my-cool-community']);

        $req = $this->prepareRequest(['name' => 'My Cool Community']);

        $this->assertSame('my-cool-community-1', $req->input('slug'));
    }

    public function test_slug_generation_increments_until_unique(): void
    {
        Community::factory()->create(['slug' => 'test']);
        Community::factory()->create(['slug' => 'test-1']);
        Community::factory()->create(['slug' => 'test-2']);

        $req = $this->prepareRequest(['name' => 'Test']);

        $this->assertSame('test-3', $req->input('slug'));
    }

    public function test_slug_not_generated_when_already_provided(): void
    {
        $req = $this->prepareRequest(['name' => 'My Name', 'slug' => 'custom-slug']);

        $this->assertSame('custom-slug', $req->input('slug'));
    }

    public function test_slug_not_generated_when_no_name(): void
    {
        $req = $this->prepareRequest([]);

        $this->assertNull($req->input('slug'));
    }

    public function test_rules_include_expected_fields(): void
    {
        $req = new CreateCommunityRequest();
        $rules = $req->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('slug', $rules);
        $this->assertArrayHasKey('price', $rules);
        $this->assertArrayHasKey('affiliate_commission_rate', $rules);
    }

    public function test_authorize_returns_true(): void
    {
        $req = new CreateCommunityRequest();
        $this->assertTrue($req->authorize());
    }
}
