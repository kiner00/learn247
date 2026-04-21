<?php

namespace App\Actions\Curzzo;

use App\Contracts\FileStorage;
use App\Models\Curzzo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateCurzzo
{
    public function __construct(private FileStorage $storage) {}

    /**
     * @param  array<string, mixed>  $data  Validated attributes. May contain `avatar` / `cover_image` as UploadedFile,
     *                                      or `remove_avatar` / `remove_cover_image` / `remove_preview_video` flags.
     */
    public function execute(Curzzo $curzzo, array $data): Curzzo
    {
        // Avatar
        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $this->storage->delete($curzzo->avatar);
            $data['avatar'] = $this->storage->upload($data['avatar'], 'curzzo-avatars');
        } elseif (! empty($data['remove_avatar'])) {
            $this->storage->delete($curzzo->avatar);
            $data['avatar'] = null;
        }
        unset($data['remove_avatar']);

        // Cover image
        if (isset($data['cover_image']) && $data['cover_image'] instanceof UploadedFile) {
            $this->storage->delete($curzzo->cover_image);
            $data['cover_image'] = $this->storage->upload($data['cover_image'], 'curzzo-covers');
        } elseif (! empty($data['remove_cover_image'])) {
            $this->storage->delete($curzzo->cover_image);
            $data['cover_image'] = null;
        }
        unset($data['remove_cover_image']);

        // Preview video
        if (! empty($data['remove_preview_video'])) {
            $data['preview_video'] = null;
        } elseif (! empty($data['preview_video']) && is_string($data['preview_video']) && $data['preview_video'] !== $curzzo->preview_video) {
            $data['preview_video'] = Storage::disk('s3')->url($data['preview_video']);
        }
        unset($data['remove_preview_video']);

        $curzzo->update($data);

        return $curzzo;
    }
}
