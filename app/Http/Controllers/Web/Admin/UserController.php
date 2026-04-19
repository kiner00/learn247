<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Admin\ResendOnboardingEmail;
use App\Actions\Admin\ToggleUserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Admin\ListUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request, ListUsers $query): Response
    {
        return Inertia::render('Admin/Users', $query->execute(
            $request->string('search')->trim()->toString()
        ));
    }

    public function toggleStatus(User $user, ToggleUserStatus $action): RedirectResponse
    {
        $action->execute($user);

        return back()->with('success', "User {$user->name} has been ".($user->is_active ? 'enabled' : 'disabled').'.');
    }

    public function resendOnboardingEmail(User $user, ResendOnboardingEmail $action): RedirectResponse
    {
        $action->execute($user);

        return back()->with('success', "Resent login email to {$user->email}.");
    }
}
