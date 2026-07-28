<?php

declare(strict_types=1);

namespace App\Enums;

enum MilestoneStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Overdue = 'overdue';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Overdue => 'Overdue',
            self::Skipped => 'Skipped',
        };
    }
}
