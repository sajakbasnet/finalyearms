<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Suspended = 'suspended';
    case Deleting = 'deleting';
    case Deleted = 'deleted';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Whether the tenant should be reachable by its users. */
    public function isServing(): bool
    {
        return $this === self::Active;
    }
}
