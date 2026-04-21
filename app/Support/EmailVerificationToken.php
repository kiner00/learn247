<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

class EmailVerificationToken
{
    private const TTL_SECONDS = 3600;

    public static function issue(User $user): string
    {
        $payload = self::base64UrlEncode(json_encode([
            'id' => $user->id,
            'email' => $user->email,
            'expires' => Carbon::now()->getTimestamp() + self::TTL_SECONDS,
        ], JSON_THROW_ON_ERROR));

        return $payload.'.'.self::sign($payload);
    }

    /**
     * Decode + verify a token. Returns the payload array on success.
     *
     * @throws InvalidEmailVerificationToken
     */
    public static function parse(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new InvalidEmailVerificationToken('Malformed token.');
        }

        [$payload, $signature] = $parts;

        if (! hash_equals(self::sign($payload), $signature)) {
            throw new InvalidEmailVerificationToken('Invalid token signature.');
        }

        $decoded = json_decode(self::base64UrlDecode($payload), true);
        if (! is_array($decoded) || ! isset($decoded['id'], $decoded['email'], $decoded['expires'])) {
            throw new InvalidEmailVerificationToken('Malformed token payload.');
        }

        if (Carbon::now()->getTimestamp() >= (int) $decoded['expires']) {
            throw new InvalidEmailVerificationToken('This verification link has expired.');
        }

        return $decoded;
    }

    private static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, config('app.key'));
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padded = str_pad($value, strlen($value) + (4 - strlen($value) % 4) % 4, '=');

        return base64_decode(strtr($padded, '-_', '+/')) ?: '';
    }
}
