<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\AuthenticateUser;
use App\Actions\Auth\RegisterUser;
use App\Actions\Auth\ResetPassword;
use App\Actions\Auth\SendEmailVerification;
use App\Actions\Auth\SendPasswordResetLink;
use App\Actions\Auth\VerifyEmail;
use App\Actions\Auth\VerifyResetToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Http\Requests\VerifyResetTokenRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthenticateUser $action): JsonResponse
    {
        $validated = $request->validated();
        $user = $action->execute($validated['email'], $validated['password']);
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function register(RegisterRequest $request, RegisterUser $action): JsonResponse
    {
        $user = $action->execute($request->validated());
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
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

    public function forgotPassword(ForgotPasswordRequest $request, SendPasswordResetLink $action): JsonResponse
    {
        $action->execute($request->validated('email'));

        return response()->json([
            'message' => "If that email is registered, we've sent a reset link. Check your inbox.",
        ]);
    }

    public function verifyResetToken(VerifyResetTokenRequest $request, VerifyResetToken $action): JsonResponse
    {
        $valid = $action->execute(
            $request->validated('email'),
            $request->validated('token'),
        );

        return response()->json(['valid' => $valid]);
    }

    public function resetPassword(ResetPasswordRequest $request, ResetPassword $action): JsonResponse
    {
        $status = $action->execute(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password reset successfully.']);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    public function sendEmailVerification(Request $request, SendEmailVerification $action): JsonResponse
    {
        $action->execute($request->user());

        return response()->json(['message' => 'Verification email sent.']);
    }

    public function verifyEmail(VerifyEmailRequest $request, VerifyEmail $action): JsonResponse
    {
        $user = $action->execute($request->validated('token'));

        return response()->json([
            'message' => 'Email verified.',
            'user' => new UserResource($user),
        ]);
    }
}
