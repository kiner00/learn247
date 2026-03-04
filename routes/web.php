<?php

use App\Http\Controllers\Web\CommunityController;
use App\Http\Middleware\EnsureActiveMembership;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

// Stub login route (auth UI provided by front-end; named route required by middleware)
Route::get('/login', fn () => view('welcome'))->name('login');

// Community listing (public)
Route::get('/communities', [CommunityController::class, 'index'])->name('communities.index');

Route::middleware('auth')->group(function () {
    Route::post('/communities', [CommunityController::class, 'store'])->name('communities.store');
    Route::post('/communities/{community}/join', [CommunityController::class, 'join'])->name('communities.join');

    // Show page — auth needed to include membership status
    Route::get('/communities/{community}', [CommunityController::class, 'show'])->name('communities.show');

    // Gated: active membership required for members list + settings
    Route::middleware(EnsureActiveMembership::class)->group(function () {
        Route::get('/communities/{community}/members', [CommunityController::class, 'members'])->name('communities.members');
        Route::get('/communities/{community}/settings', [CommunityController::class, 'settings'])->name('communities.settings');
    });
});
