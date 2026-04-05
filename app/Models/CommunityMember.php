<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CommunityMember extends Model
{
    use HasFactory;

    public const ROLE_ADMIN     = 'admin';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_MEMBER    = 'member';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_MODERATOR, self::ROLE_MEMBER];

    public const MEMBERSHIP_FREE = 'free';
    public const MEMBERSHIP_PAID = 'paid';

    protected $fillable = ['community_id', 'user_id', 'role', 'membership_type', 'expires_at', 'points', 'joined_at', 'notif_prefs', 'chat_enabled', 'show_on_profile', 'is_blocked'];

    // Points per action
    public const POINTS_POST    = 10;
    public const POINTS_COMMENT = 5;
    public const POINTS_LESSON  = 20;

    // Level thresholds — 9 levels (index = level - 1)
    public const LEVEL_THRESHOLDS = [0, 5, 20, 65, 155, 515, 2015, 8015, 33015];

    public static function computeLevel(int $points): int
    {
        $thresholds = array_reverse(self::LEVEL_THRESHOLDS);
        foreach ($thresholds as $i => $threshold) {
            if ($points >= $threshold) {
                return count(self::LEVEL_THRESHOLDS) - $i;
            }
        }
        return 1;
    }

    public function awardPoints(int $pts): void
    {
        $this->increment('points', max(0, $pts));
    }

    public function deductPoints(int $pts): void
    {
        $this->decrement('points', min($pts, $this->points));
    }

    protected function casts(): array
    {
        return [
            'joined_at'       => 'datetime',
            'expires_at'      => 'datetime',
            'notif_prefs'     => 'array',
            'chat_enabled'    => 'boolean',
            'show_on_profile' => 'boolean',
            'is_blocked'      => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'community_member_tag')
                    ->withPivot('tagged_at');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isModerator(): bool
    {
        return $this->role === self::ROLE_MODERATOR;
    }

    public function canModerate(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MODERATOR]);
    }

    public function isBlocked(): bool
    {
        return (bool) $this->is_blocked;
    }
}
