<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        return back()->with('success', "If that email is registered, we've sent a reset link. Check your inbox.");
    }

    public function showReset(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->update([
                    'password'             => bcrypt($password),
                    'needs_password_setup' => false,
                ]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password reset! Please sign in.');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
