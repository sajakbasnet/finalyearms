<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Enums\ProvisioningStep;
use RuntimeException;

final class ProvisioningFailed extends RuntimeException
{
    public function __construct(
        public readonly ProvisioningStep $step,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
