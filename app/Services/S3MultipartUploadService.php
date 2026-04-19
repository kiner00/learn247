<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3MultipartUploadService
{
    /**
     * Begin a multipart upload to S3 and return the upload id + storage key.
     */
    public function initiate(string $filename, string $contentType, string $prefix): array
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin';
        $key       = trim($prefix, '/') . '/' . Str::uuid() . '.' . $extension;

        $client = Storage::disk('s3')->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $result = $client->createMultipartUpload([
            'Bucket'      => $bucket,
            'Key'         => $key,
            'ContentType' => $contentType,
        ]);

        return [
            'upload_id' => $result['UploadId'],
            'key'       => $key,
        ];
    }

    /**
     * Get a presigned URL for uploading one part.
     */
    public function partUrl(string $key, string $uploadId, int $partNumber, string $expiry = '+30 minutes'): string
    {
        $client = Storage::disk('s3')->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $command = $client->getCommand('UploadPart', [
            'Bucket'     => $bucket,
            'Key'        => $key,
            'UploadId'   => $uploadId,
            'PartNumber' => $partNumber,
        ]);

        return (string) $client->createPresignedRequest($command, $expiry)->getUri();
    }

    /**
     * Finalize a multipart upload.
     *
     * @param  array<int, array{PartNumber: int, ETag: string}>  $parts
     */
    public function complete(string $key, string $uploadId, array $parts): void
    {
        $client = Storage::disk('s3')->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $client->completeMultipartUpload([
            'Bucket'          => $bucket,
            'Key'             => $key,
            'UploadId'        => $uploadId,
            'MultipartUpload' => ['Parts' => $parts],
        ]);
    }

    public function abort(string $key, string $uploadId): void
    {
        $client = Storage::disk('s3')->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $client->abortMultipartUpload([
            'Bucket'   => $bucket,
            'Key'      => $key,
            'UploadId' => $uploadId,
        ]);
    }
}
