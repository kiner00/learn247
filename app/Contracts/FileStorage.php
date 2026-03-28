<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface FileStorage
{
    public function upload(UploadedFile $file, string $folder): string;

    public function delete(?string $url): void;
}
