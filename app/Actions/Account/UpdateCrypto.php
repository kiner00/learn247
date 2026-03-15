<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdateCrypto
{
    public function execute(User $user, ?string $cryptoWallet): void
    {
        $user->update(['crypto_wallet' => $cryptoWallet]);
    }
}
