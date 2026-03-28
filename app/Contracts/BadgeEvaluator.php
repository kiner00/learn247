<?php

namespace App\Contracts;

use App\Models\User;

interface BadgeEvaluator
{
    public function evaluate(User $user, ?int $communityId = null): void;
}
