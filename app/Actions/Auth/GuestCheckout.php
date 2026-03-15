<?php

namespace App\Actions\Auth;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuestCheckout
{
    public function findOrCreateUser(array $data): User
    {
        $user = User::where('email', $data['email'])->first();

        if ($user) {
            return $user;
        }

        $user = User::create([
            'name'                 => trim($data['first_name'] . ' ' . $data['last_name']),
            'email'                => $data['email'],
            'phone'                => $data['phone'] ?? null,
            'password'             => Hash::make(Str::random(32)),
            'needs_password_setup' => true,
        ]);

        $user->update(['username' => $this->generateUsername($data['first_name'], $data['last_name'], $user->id)]);

        return $user;
    }

    public function hasActiveSubscription(int $userId, int $communityId): bool
    {
        return Subscription::where('community_id', $communityId)
            ->where('user_id', $userId)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->exists();
    }

    private function generateUsername(string $firstName, string $lastName, int $userId): string
    {
        $slug = fn (string $s): string => trim(
            preg_replace('/-+/', '-', preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($s)))),
            '-'
        );

        $first = $slug($firstName) ?: 'user';
        $last  = $slug($lastName);
        $base  = $last ? "{$first}-{$last}" : $first;

        return "{$base}-{$userId}";
    }
}
