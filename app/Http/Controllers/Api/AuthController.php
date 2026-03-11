<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Your account has been disabled. Please contact support.'], 403);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
        ]);
    }

    public function register(Request $request): JsonResponse
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

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    private function generateUsername(string $firstName, string $lastName, int $userId): string
    {
        $slug = function (string $s): string {
            return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($s)))), '-');
        };

        $first = $slug($firstName) ?: 'user';
        $last  = $slug($lastName);
        $base  = $last ? "{$first}-{$last}" : $first;

        return "{$base}-{$userId}";
    }
}
