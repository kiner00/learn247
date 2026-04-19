<?php

namespace Tests\Feature\Ai\Tools;

use App\Ai\Tools\GetAllCommunitiesTool;
use App\Models\Community;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetAllCommunitiesToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_all_communities(): void
    {
        Community::factory()->create(['name' => 'Alpha Community', 'price' => 0]);
        Community::factory()->create(['name' => 'Beta Community', 'price' => 499, 'billing_type' => 'monthly']);

        $tool = new GetAllCommunitiesTool;
        $result = $tool->handle(new Request([]));
        $json = json_decode($result, true);

        $this->assertCount(2, $json);
        $this->assertSame('Alpha Community', $json[0]['name']);
        $this->assertSame('Free', $json[0]['price']);
        $this->assertSame('Beta Community', $json[1]['name']);
        $this->assertStringContainsString('499', $json[1]['price']);
    }

    public function test_filters_by_name_keyword(): void
    {
        Community::factory()->create(['name' => 'PHP Developers']);
        Community::factory()->create(['name' => 'Design Hub']);

        $tool = new GetAllCommunitiesTool;
        $result = $tool->handle(new Request(['search' => 'PHP']));
        $json = json_decode($result, true);

        $this->assertCount(1, $json);
        $this->assertSame('PHP Developers', $json[0]['name']);
    }

    public function test_filters_by_category_keyword(): void
    {
        Community::factory()->create(['name' => 'Code Camp', 'category' => 'programming']);
        Community::factory()->create(['name' => 'Art Space', 'category' => 'design']);

        $tool = new GetAllCommunitiesTool;
        $result = $tool->handle(new Request(['search' => 'design']));
        $json = json_decode($result, true);

        $this->assertCount(1, $json);
        $this->assertSame('Art Space', $json[0]['name']);
    }

    public function test_returns_no_communities_message_when_empty(): void
    {
        $tool = new GetAllCommunitiesTool;
        $result = $tool->handle(new Request([]));

        $this->assertStringContainsString('No communities found', $result);
    }

    public function test_returns_no_communities_message_with_search(): void
    {
        Community::factory()->create(['name' => 'Alpha']);

        $tool = new GetAllCommunitiesTool;
        $result = $tool->handle(new Request(['search' => 'nonexistent']));

        $this->assertStringContainsString('No communities found', $result);
        $this->assertStringContainsString('nonexistent', $result);
    }

    public function test_excludes_soft_deleted_communities(): void
    {
        $community = Community::factory()->create(['name' => 'Deleted Community']);
        $community->delete();

        Community::factory()->create(['name' => 'Active Community']);

        $tool = new GetAllCommunitiesTool;
        $result = $tool->handle(new Request([]));
        $json = json_decode($result, true);

        $this->assertCount(1, $json);
        $this->assertSame('Active Community', $json[0]['name']);
    }

    public function test_includes_private_flag(): void
    {
        Community::factory()->create(['name' => 'Private One', 'is_private' => true]);

        $tool = new GetAllCommunitiesTool;
        $result = $tool->handle(new Request([]));
        $json = json_decode($result, true);

        $this->assertTrue($json[0]['is_private']);
    }

    public function test_truncates_long_description(): void
    {
        Community::factory()->create([
            'name' => 'Verbose Community',
            'description' => str_repeat('A', 300),
        ]);

        $tool = new GetAllCommunitiesTool;
        $result = $tool->handle(new Request([]));
        $json = json_decode($result, true);

        $this->assertLessThanOrEqual(123, strlen($json[0]['description'])); // 120 + "..."
    }

    public function test_description_returns_string(): void
    {
        $tool = new GetAllCommunitiesTool;
        $this->assertIsString($tool->description());
    }

    public function test_schema_has_search_key(): void
    {
        $tool = new GetAllCommunitiesTool;
        $schema = $this->createMock(\Illuminate\Contracts\JsonSchema\JsonSchema::class);

        $builder = new class
        {
            public function description($d)
            {
                return $this;
            }
        };

        $schema->method('string')->willReturn($builder);

        $result = $tool->schema($schema);
        $this->assertArrayHasKey('search', $result);
    }
}
