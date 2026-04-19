<?php

namespace App\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class HlsManifestRewriter
{
    /**
     * Serve a single HLS asset (.m3u8 or .ts) from S3.
     *
     * For playlists, relative segment / variant references are rewritten
     * through the supplied proxy URL builder so the client never touches S3
     * directly. For .ts segments, the request is redirected to a short-lived
     * signed S3 URL.
     *
     * @param  string  $hlsPrefix  The S3 prefix where the HLS output lives.
     * @param  string  $file  Requested file path relative to the prefix (e.g. "video.m3u8" or "video_720p_00001.ts").
     * @param  callable(string): string  $urlBuilder  Receives a relative file path and returns the fully-qualified proxy URL.
     */
    public function serve(string $hlsPrefix, string $file, callable $urlBuilder): Response|RedirectResponse
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (! in_array($ext, ['m3u8', 'ts'], true)) {
            abort(400, 'Invalid HLS file type.');
        }

        $hlsPrefix = rtrim($hlsPrefix, '/');
        $s3Key = $hlsPrefix.'/'.ltrim($file, '/');

        // Reject path traversal attempts: the canonical key must still live under the prefix.
        $normalized = $this->normalizePath($s3Key);
        if (! str_starts_with($normalized, $hlsPrefix.'/')) {
            abort(403);
        }

        if (! Storage::exists($normalized)) {
            abort(404);
        }

        if ($ext === 'm3u8') {
            $content = Storage::get($normalized);
            $proxyDir = dirname($file);
            $prefixUrl = $proxyDir !== '.' && $proxyDir !== '' ? rtrim($proxyDir, '/').'/' : '';

            $rewritten = preg_replace_callback(
                '/^(?!#)(.+\.(ts|m3u8))$/m',
                fn (array $m) => $urlBuilder($prefixUrl.$m[1]),
                $content,
            );

            return response($rewritten, 200, [
                'Content-Type' => 'application/vnd.apple.mpegurl',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        // .ts segment — redirect to signed S3 URL (2h)
        return redirect(Storage::temporaryUrl($normalized, now()->addHours(2)));
    }

    /**
     * Collapse "." and ".." segments without touching the filesystem.
     */
    private function normalizePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $segment;
        }

        return implode('/', $parts);
    }
}
