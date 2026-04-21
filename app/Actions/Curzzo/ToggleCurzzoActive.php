<?php

namespace App\Actions\Curzzo;

use App\Models\Curzzo;

class ToggleCurzzoActive
{
    public function execute(Curzzo $curzzo): Curzzo
    {
        $curzzo->update(['is_active' => ! $curzzo->is_active]);

        return $curzzo;
    }
}
