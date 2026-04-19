<?php

namespace App\Actions\Community;

use App\Models\Affiliate;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Services\StorageService;
use App\Support\AffiliateCodeGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CreateCommunity
{
    public function __construct(private StorageService $storage) {}

    public function execute(User $user, array $data, ?UploadedFile $avatar = null, ?UploadedFile $coverImage = null): Community
    {
        if ($coverImage) {
            $data['cover_image'] = $this->storage->upload($coverImage, 'community-covers');
        }

        if ($avatar) {
            $data['avatar'] = $this->storage->upload($avatar, 'community-avatars');
        }

        $community = Community::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'owner_id' => $user->id,
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'is_private' => $data['is_private'] ?? false,
            'price' => $data['price'] ?? 0,
            'currency' => $data['currency'] ?? 'PHP',
            'billing_type' => $data['billing_type'] ?? 'monthly',
            'affiliate_commission_rate' => $data['affiliate_commission_rate'] ?? null,
        ]);

        // Owner is automatically an admin member
        CommunityMember::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_ADMIN,
            'joined_at' => now(),
        ]);

        // Owner gets an affiliate/invite code automatically
        Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'code' => AffiliateCodeGenerator::generate(),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        return $community;
    }
}
