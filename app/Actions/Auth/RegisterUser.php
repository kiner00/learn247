<?php

namespace App\Actions\Auth;

use App\Contracts\BadgeEvaluator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterUser
{
    public function __construct(private BadgeEvaluator $badges) {}

    public function execute(array $data): User
    {
        $user = User::create([
            'name'     => trim($data['first_name'] . ' ' . $data['last_name']),
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        $user->update(['username' => $this->generateUsername($data['first_name'], $data['last_name'], $user->id)]);

        $this->badges->evaluate($user);

        return $user;
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
