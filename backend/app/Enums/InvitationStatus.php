<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a team invitation.
 *
 * Declined and cancelled are kept rather than deleted so a lead can see who was
 * asked and what came back, instead of an invitation silently vanishing.
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Whether the invitee can still act on it. */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
