<?php

namespace App\Contracts;

interface Transcodeable
{
    public function getVideoPath(): ?string;

    public function setTranscodeStatus(string $status, int $percent): void;

    public function setHlsPath(string $path): void;

    public function setPosterPath(?string $path): void;

    public function getHlsPathPrefix(): string;

    public function getTranscodeIdentifier(): string;
}
