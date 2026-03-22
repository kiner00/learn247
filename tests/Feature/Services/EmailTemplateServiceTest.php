<?php

namespace Tests\Feature\Services;

use App\Models\EmailTemplate;
use App\Services\Admin\EmailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailTemplateService();
    }

    private function makeTemplate(string $key, array $overrides = []): EmailTemplate
    {
        return EmailTemplate::updateOrCreate(
            ['key' => $key],
            array_merge([
                'name'      => "Template {$key}",
                'subject'   => 'Subject',
                'html_body' => '<p>Body</p>',
                'variables' => [],
            ], $overrides)
        );
    }

    public function test_substitutes_all_template_variables(): void
    {
        $this->makeTemplate('test_subst', [
            'html_body' => '<p>Hello {{name}}, your email is {{email}}.</p>',
            'variables' => ['name' => 'Recipient name', 'email' => 'Recipient email'],
        ]);

        $result = $this->service->preview('test_subst', '<p>Hello {{name}}, your email is {{email}}.</p>');

        $this->assertSame('<p>Hello [name], your email is [email].</p>', $result);
    }

    public function test_variables_replaced_with_bracketed_placeholder(): void
    {
        $this->makeTemplate('test_bracket', [
            'html_body' => '{{community_name}} awaits!',
            'variables' => ['community_name' => 'Community name'],
        ]);

        $result = $this->service->preview('test_bracket', '{{community_name}} awaits!');

        $this->assertSame('[community_name] awaits!', $result);
    }

    public function test_returns_body_unchanged_when_no_variables(): void
    {
        $this->makeTemplate('test_novars', [
            'html_body' => '<p>Static content.</p>',
            'variables' => [],
        ]);

        $result = $this->service->preview('test_novars', '<p>Static content.</p>');

        $this->assertSame('<p>Static content.</p>', $result);
    }

    public function test_returns_body_unchanged_when_variables_is_null(): void
    {
        $this->makeTemplate('test_nullvars', [
            'html_body' => '<p>Hello there.</p>',
            'variables' => null,
        ]);

        $result = $this->service->preview('test_nullvars', '<p>Hello there.</p>');

        $this->assertSame('<p>Hello there.</p>', $result);
    }

    public function test_variable_not_present_in_body_is_silently_skipped(): void
    {
        $this->makeTemplate('test_partial', [
            'html_body' => '<p>Hello.</p>',
            'variables' => ['name' => 'Name', 'email' => 'Email'],
        ]);

        $result = $this->service->preview('test_partial', '<p>Hello.</p>');

        $this->assertSame('<p>Hello.</p>', $result);
    }

    public function test_throws_when_template_key_does_not_exist(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->preview('nonexistent_key_xyz', '<p>Body</p>');
    }

    public function test_uses_body_argument_not_stored_html_body(): void
    {
        $this->makeTemplate('test_override', [
            'html_body' => '<p>Old body {{name}}</p>',
            'variables' => ['name' => 'Name'],
        ]);

        // Pass a different body — simulating an unsaved draft preview
        $result = $this->service->preview('test_override', '<p>New body {{name}}</p>');

        $this->assertSame('<p>New body [name]</p>', $result);
    }
}
