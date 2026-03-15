<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateProfile
{
    public function execute(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        $data['name'] = trim($data['first_name'] . ' ' . $data['last_name']);
        unset($data['first_name'], $data['last_name']);

        if ($avatar) {
            if ($user->avatar && str_starts_with($user->avatar, '/storage/')) {
                Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $user->avatar), '/'));
            }
            $path = $avatar->store('user-avatars', 'public');
            $data['avatar'] = Storage::url($path);
        } else {
            unset($data['avatar']);
        }

        $user->update($data);

        return $user->refresh();
    }
}
