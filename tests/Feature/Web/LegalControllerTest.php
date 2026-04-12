<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_returns_200(): void
    {
        $this->get('/terms')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Legal/Terms'));
    }

    public function test_privacy_page_returns_200(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Legal/Privacy'));
    }

    public function test_terms_page_accessible_without_auth(): void
    {
        // No actingAs — guest access should work
        $this->get(route('terms'))->assertOk();
    }

    public function test_privacy_page_accessible_without_auth(): void
    {
        $this->get(route('privacy'))->assertOk();
    }
}
