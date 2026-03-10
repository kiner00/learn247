<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class SetPasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/SetPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password'             => Hash::make($request->password),
            'needs_password_setup' => false,
        ])->save();

        return redirect('/communities')->with('success', 'Password updated! Welcome to Curzzo.');
    }
}
