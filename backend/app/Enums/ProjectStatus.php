<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case ProposalPending = 'proposal_pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case OnHold = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::ProposalPending => 'Proposal Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::OnHold => 'On Hold',
        };
    }
}
