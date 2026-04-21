<?php

namespace App\Actions\Account;

use App\Models\User;
use App\Services\StorageService;
use Illuminate\Support\Facades\DB;

class HardDeleteUserAccount
{
    public function __construct(private readonly StorageService $storage) {}

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->wipeStoredFiles($user);
            $user->forceDelete();
        });
    }

    private function wipeStoredFiles(User $user): void
    {
        foreach ([$user->avatar, $user->kyc_id_document, $user->kyc_selfie] as $url) {
            if ($url) {
                $this->storage->delete($url);
            }
        }
    }
}
