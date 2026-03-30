<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class UpdateCommunity
{
    public function __construct(private StorageService $storage) {}

    /**
     * @throws ValidationException
     */
    public function execute(Community $community, array $data, ?UploadedFile $avatar = null, ?UploadedFile $coverImage = null): Community
    {
        $newPrice = isset($data['price']) ? (float) $data['price'] : null;
        $oldPrice = (float) $community->price;

        if ($newPrice !== null && $newPrice > 0 && $oldPrice <= 0) {
            $this->validatePricingGate($community, $data, $coverImage);
        }

        if ($coverImage) {
            $this->storage->delete($community->cover_image);
            $data['cover_image'] = $this->storage->upload($coverImage, 'community-covers');
        } else {
            unset($data['cover_image']);
        }

        if ($avatar) {
            $this->storage->delete($community->avatar);
            $data['avatar'] = $this->storage->upload($avatar, 'community-avatars');
        } else {
            unset($data['avatar']);
        }

        $community->update($data);

        return $community->refresh();
    }

    private function validatePricingGate(Community $community, array $data, ?UploadedFile $coverImage): void
    {
        $moduleCount = $community->courses()
            ->withCount(['modules' => fn ($q) => $q->where('is_free', false)])
            ->get()->sum('modules_count');
        $owner       = $community->owner;

        if ($moduleCount < 5) {
            throw ValidationException::withMessages([
                'price' => "You need at least 5 modules to enable pricing (you have {$moduleCount}).",
            ]);
        }

        if (empty($community->cover_image) && ! $coverImage) {
            throw ValidationException::withMessages([
                'price' => 'Upload a banner image before enabling pricing.',
            ]);
        }

        if (empty(trim($data['description'] ?? $community->description ?? ''))) {
            throw ValidationException::withMessages([
                'price' => 'Add a community description before enabling pricing.',
            ]);
        }

        if (! ($owner && $owner->name && $owner->bio && $owner->avatar)) {
            throw ValidationException::withMessages([
                'price' => 'Complete your profile (name, bio, avatar) before enabling pricing.',
            ]);
        }
    }
}
