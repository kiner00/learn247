<?php

namespace App\Actions\Curzzo;

use App\Contracts\FileStorage;
use App\Models\Community;
use App\Models\Curzzo;
use App\Models\User;
use App\Services\Community\PlanLimitService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreateCurzzo
{
    public function __construct(
        private PlanLimitService $planLimit,
        private FileStorage $storage,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated attributes. May contain `avatar` and `cover_image` as UploadedFile.
     *
     * @throws ValidationException
     */
    public function execute(User $creator, Community $community, array $data): Curzzo
    {
        if (! $this->planLimit->canCreateCurzzo($creator, $community)) {
            throw ValidationException::withMessages([
                'plan' => 'Curzzos require a Pro plan (max 5 per community).',
            ]);
        }

        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $data['avatar'] = $this->storage->upload($data['avatar'], 'curzzo-avatars');
        }

        if (isset($data['cover_image']) && $data['cover_image'] instanceof UploadedFile) {
            $data['cover_image'] = $this->storage->upload($data['cover_image'], 'curzzo-covers');
        }

        if (! empty($data['preview_video']) && is_string($data['preview_video'])) {
            $data['preview_video'] = Storage::disk('s3')->url($data['preview_video']);
        }

        $data['community_id'] = $community->id;
        $data['position'] = $community->curzzos()->count();

        return Curzzo::create($data);
    }
}
