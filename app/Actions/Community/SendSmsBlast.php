<?php

namespace App\Actions\Community;

use App\Contracts\SmsProvider;
use App\Models\Community;

class SendSmsBlast
{
    public function __construct(private SmsProvider $sms) {}

    /**
     * Resolve the recipient phone numbers for the given filter, then send the blast.
     * Returns ['sent' => int, 'failed' => int, 'no_recipients' => bool].
     */
    public function execute(Community $community, array $data): array
    {
        $query = $community->members()
            ->join('users', 'users.id', '=', 'community_members.user_id')
            ->whereNotNull('users.phone')
            ->where('users.phone', '!=', '');

        if ($data['filter_type'] === 'new_members') {
            $days = $data['filter_days'] ?? 7;
            $query->where('community_members.joined_at', '>=', now()->subDays($days));
        } elseif ($data['filter_type'] === 'course') {
            $courseId = $data['filter_course_id'];
            $query->whereExists(function ($q) use ($courseId) {
                $q->from('course_enrollments')
                    ->whereColumn('course_enrollments.user_id', 'users.id')
                    ->where('course_enrollments.course_id', $courseId)
                    ->where('course_enrollments.status', 'paid');
            });
        }

        $numbers = $query->pluck('users.phone')
            ->map(fn ($p) => preg_replace('/\D/', '', $p))
            ->filter(fn ($p) => strlen($p) >= 10)
            ->values()
            ->toArray();

        if (empty($numbers)) {
            return ['sent' => 0, 'failed' => 0, 'no_recipients' => true];
        }

        return array_merge(
            $this->sms->blast($community, $numbers, $data['message']),
            ['no_recipients' => false]
        );
    }
}
