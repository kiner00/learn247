<?php

namespace App\Queries\AI;

use App\Models\User;

class BuildAIContext
{
    public function execute(User $user): array
    {
        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ];
    }
}
