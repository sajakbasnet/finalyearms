<?php

declare(strict_types=1);

namespace App\Enums;

enum JobState: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
