<?php

namespace App\Exceptions;

use RuntimeException;

class AiBudgetExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $scope,
        public readonly int $scopeId,
        public readonly float $spent,
        public readonly float $cap,
        public readonly int $windowMinutes,
    ) {
        parent::__construct(
            "AI budget exceeded for {$scope}={$scopeId}: \$".number_format($spent, 4)." in last {$windowMinutes}m (cap \$".number_format($cap, 2).')'
        );
    }
}
