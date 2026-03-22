<?php

namespace App\Actions\Community;

use App\Jobs\SendBatchInvites;
use App\Mail\CommunityInviteMail;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\CommunityMember;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendInvite
{
    /**
     * @return array{type: string, message: string}
     */
    public function single(Community $community, string $email, ?int $freeAccessMonths = null): array
    {
        $email = strtolower(trim($email));

        $alreadyMember = CommunityMember::where('community_id', $community->id)
            ->whereHas('user', fn ($q) => $q->where('email', $email))
            ->exists();

        if ($alreadyMember) {
            return ['type' => 'error', 'message' => "{$email} is already a member."];
        }

        $invite = CommunityInvite::updateOrCreate(
            ['community_id' => $community->id, 'email' => $email],
            [
                'token'               => Str::random(64),
                'accepted_at'         => null,
                'expires_at'          => now()->addDays(7),
                'free_access_months'  => $freeAccessMonths,
            ]
        );

        Mail::to($email)->queue(new CommunityInviteMail($invite));

        return ['type' => 'success', 'message' => "Invite sent to {$email}."];
    }

    /**
     * @return array{type: string, message: string}
     */
    public function batch(Community $community, array $emails, ?int $freeAccessMonths = null): array
    {
        $emails = array_unique(array_map('strtolower', $emails));

        if (empty($emails)) {
            return ['type' => 'error', 'message' => 'No valid email addresses found in the CSV.'];
        }

        SendBatchInvites::dispatch($community, $emails, $freeAccessMonths);

        $count = count($emails);

        return [
            'type'    => 'success',
            'message' => "{$count} invite" . ($count !== 1 ? 's' : '') . " queued — emails will arrive shortly.",
        ];
    }

    /**
     * Parse CSV file and return valid email addresses.
     */
    public function parseCSV(string $filePath): array
    {
        $emails = [];
        $lines  = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $col = trim(str_getcsv($line)[0] ?? '');
            if (filter_var($col, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($col);
            }
        }

        return array_unique($emails);
    }
}
