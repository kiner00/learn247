<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Queries\Badge\GetBadges;
use Inertia\Inertia;
use Inertia\Response;

class BadgeController extends Controller
{
    public function index(GetBadges $query): Response
    {
        $badges = $query->execute(auth()->id());

        return Inertia::render('Badges/Index', [
            'memberBadges'  => $badges['member'],
            'creatorBadges' => $badges['creator'],
        ]);
    }
}
