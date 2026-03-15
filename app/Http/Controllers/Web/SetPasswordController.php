<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\SetPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class SetPasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/SetPassword');
    }

    public function store(Request $request, SetPassword $action): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $action->execute($request->user(), $request->password);

        return redirect('/communities')->with('success', 'Password updated! Welcome to Curzzo.');
    }
}
