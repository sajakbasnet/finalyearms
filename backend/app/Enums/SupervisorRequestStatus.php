<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a request for a supervisor.
 *
 * Requests live in their own table rather than as a status on
 * `supervisor_assignments`: that table is unique per (student, session), so a
 * declined request would permanently occupy the slot and block re-requesting.
 * Here a team can be declined and ask someone else, and the trail survives.
 */
enum SupervisorRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    /** A settled request no longer blocks a project from asking elsewhere. */
    public function isSettled(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
