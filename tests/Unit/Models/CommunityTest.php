<?php

namespace Tests\Unit\Models;

use App\Models\Community;
use PHPUnit\Framework\TestCase;

class CommunityTest extends TestCase
{
    public function test_is_free_returns_true_when_price_is_zero(): void
    {
        $community        = new Community();
        $community->price = 0;

        $this->assertTrue($community->isFree());
    }

    public function test_is_free_returns_true_when_price_is_negative(): void
    {
        $community        = new Community();
        $community->price = -1;

        $this->assertTrue($community->isFree());
    }

    public function test_is_free_returns_false_when_price_is_positive(): void
    {
        $community        = new Community();
        $community->price = 499;

        $this->assertFalse($community->isFree());
    }

    public function test_route_key_name_is_slug(): void
    {
        $community = new Community();

        $this->assertSame('slug', $community->getRouteKeyName());
    }
}
