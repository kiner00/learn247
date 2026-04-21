<?php

namespace App\Actions\Curzzo;

use App\Contracts\FileStorage;
use App\Models\Curzzo;

class DeleteCurzzo
{
    public function __construct(private FileStorage $storage) {}

    public function execute(Curzzo $curzzo): void
    {
        $this->storage->delete($curzzo->avatar);
        $this->storage->delete($curzzo->cover_image);
        $curzzo->delete();
    }
}
