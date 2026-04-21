<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\ResetPassword;
use App\Actions\Auth\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function send(ForgotPasswordRequest $request, SendPasswordResetLink $action): RedirectResponse
    {
        $action->execute($request->validated('email'));

        return back()->with('success', "If that email is registered, we've sent a reset link. Check your inbox.");
    }

    public function showReset(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(ResetPasswordRequest $request, ResetPassword $action): RedirectResponse
    {
        $status = $action->execute(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password reset! Please sign in.');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
