<?php

namespace App\Actions\Billing;

use App\Models\CurzzoPurchase;

class CheckCurzzoPurchaseStatus
{
    public function execute(CurzzoPurchase $purchase): CurzzoPurchase
    {
        return $purchase->fresh();
    }
}
