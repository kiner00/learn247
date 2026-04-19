<?php

namespace App\Actions\Account;

use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;

class UpdateProfile
{
    public function __construct(private StorageService $storage) {}

    public function execute(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        $data['name'] = trim($data['first_name'].' '.$data['last_name']);
        unset($data['first_name'], $data['last_name']);

        if ($avatar) {
            $this->storage->delete($user->avatar);
            $data['avatar'] = $this->storage->upload($avatar, 'user-avatars');
        } else {
            unset($data['avatar']);
        }

        $user->update($data);

        return $user->refresh();
    }
}
