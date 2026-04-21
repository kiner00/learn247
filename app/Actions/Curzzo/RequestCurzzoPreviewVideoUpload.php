<?php

namespace App\Actions\Curzzo;

use App\Models\Community;
use App\Services\Community\PlanLimitService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RequestCurzzoPreviewVideoUpload
{
    public function __construct(private PlanLimitService $planLimit) {}

    /**
     * @return array{upload_url: string, key: string}
     *
     * @throws HttpResponseException 403 when owner is not on Pro, 422 when size exceeds plan cap.
     */
    public function execute(Community $community, string $filename, string $contentType, int $size): array
    {
        $owner = $community->owner;

        if (! $this->planLimit->canUploadVideo($owner)) {
            throw new HttpResponseException(
                response()->json(['error' => 'Preview video uploads require a Pro plan.'], 403)
            );
        }

        $maxMb = $this->planLimit->maxVideoSizeMb($owner->creatorPlan());

        if ($size > $maxMb * 1024 * 1024) {
            throw new HttpResponseException(
                response()->json(['error' => "File too large. Maximum size is {$maxMb}MB."], 422)
            );
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'mp4';
        $key = 'curzzo-previews/'.Str::uuid().'.'.$extension;

        $client = Storage::disk('s3')->getClient();
        $command = $client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $key,
            'ContentType' => $contentType,
        ]);

        $presigned = $client->createPresignedRequest($command, '+30 minutes');

        return [
            'upload_url' => (string) $presigned->getUri(),
            'key' => $key,
        ];
    }
}
