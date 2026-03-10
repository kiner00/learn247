<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class SetPasswordController extends Controller
{
    public function show(Request $request): mixed
    {
        $token = $request->query('token');
        $email = $request->query('email');

        if (! $token || ! $email) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/SetPassword', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'             => Hash::make($password),
                    'needs_password_setup' => false,
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        // Log the user in after setting password
        $user = User::where('email', $request->email)->first();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/communities')->with('success', 'Password set! Welcome to Curzzo.');
    }
}
