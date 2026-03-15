<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\AuthenticateUser;
use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        if ($redirect = $request->query('redirect')) {
            session()->put('url.intended', url($redirect));
        }

        return Inertia::render('Auth/Login');
    }

    public function login(Request $request, AuthenticateUser $action): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = $action->execute($credentials['email'], $credentials['password']);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($user->needs_password_setup) {
            return redirect()->route('password.setup');
        }

        return redirect()->intended('/communities')->with('show_ai_greeting', true);
    }

    public function showRegister(Request $request): Response
    {
        if ($redirect = $request->query('redirect')) {
            session()->put('url.intended', url($redirect));
        }

        return Inertia::render('Auth/Register');
    }

    public function register(Request $request, RegisterUser $action): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'password'   => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $action->execute($data);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/communities')->with('success', 'Welcome to Curzzo!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
