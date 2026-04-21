<?php

namespace App\Actions\Curzzo;

/**
 * Result of a Curzzo chat attempt — status code and JSON body.
 * Web and API controllers wrap this identically into a response.
 */
final readonly class ChatResult
{
    public function __construct(
        public int $status,
        public array $body,
    ) {}
}
