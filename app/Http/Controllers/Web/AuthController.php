<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\AuthenticateUser;
use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function login(LoginRequest $request, AuthenticateUser $action): RedirectResponse
    {
        $validated = $request->validated();
        $user = $action->execute($validated['email'], $validated['password']);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($user->needs_password_setup) {
            return redirect()->route('password.setup');
        }

        $default = request()->attributes->has('domain_community') ? '/' : '/communities';

        return redirect()->intended($default)->with('show_ai_greeting', true);
    }

    public function showRegister(Request $request): Response
    {
        if ($redirect = $request->query('redirect')) {
            session()->put('url.intended', url($redirect));
        }

        return Inertia::render('Auth/Register');
    }

    public function register(RegisterRequest $request, RegisterUser $action): RedirectResponse
    {
        $user = $action->execute($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        $default = request()->attributes->has('domain_community') ? '/' : '/communities';

        return redirect()->intended($default)->with('success', 'Welcome to Curzzo!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
