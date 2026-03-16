<?php

namespace App\Actions\Community;

use App\Models\Community;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UpdateCommunity
{
    /**
     * @throws ValidationException
     */
    public function execute(Community $community, array $data, ?UploadedFile $avatar = null, ?UploadedFile $coverImage = null): Community
    {
        if (isset($data['price']) && (float) $data['price'] > 0) {
            $this->validatePricingGate($community, $data, $coverImage);
        }

        if ($coverImage) {
            $this->deleteOldFile($community->cover_image);
            $path = $coverImage->store('community-covers', 'public');
            $data['cover_image'] = Storage::url($path);
        } else {
            unset($data['cover_image']);
        }

        if ($avatar) {
            $this->deleteOldFile($community->avatar);
            $path = $avatar->store('community-avatars', 'public');
            $data['avatar'] = Storage::url($path);
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

    private function deleteOldFile(?string $url): void
    {
        if ($url && str_starts_with($url, '/storage/')) {
            Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $url), '/'));
        }
    }
}
