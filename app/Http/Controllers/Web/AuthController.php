<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();

        return redirect()->intended('/communities');
    }

    public function showRegister(Request $request): Response
    {
        if ($redirect = $request->query('redirect')) {
            session()->put('url.intended', url($redirect));
        }

        return Inertia::render('Auth/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'password'   => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name'     => trim($data['first_name'] . ' ' . $data['last_name']),
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->update(['username' => $this->generateUsername($data['first_name'], $data['last_name'], $user->id)]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/communities')->with('success', 'Welcome to Curzzo!');
    }

    private function generateUsername(string $firstName, string $lastName, int $userId): string
    {
        $slug = function (string $s): string {
            return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($s)))), '-');
        };

        $first = $slug($firstName) ?: 'user';
        $last  = $slug($lastName);

        $base = $last ? "{$first}-{$last}" : $first;

        return "{$base}-{$userId}";
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
