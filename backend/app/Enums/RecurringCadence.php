<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How often a recurring log item repeats.
 *
 * Expressed in days so that applying a template to a project is arithmetic on
 * the project start date, with no calendar-library dependency.
 */
enum RecurringCadence: string
{
    case Weekly = 'weekly';
    case Fortnightly = 'fortnightly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::Fortnightly => 'Every two weeks',
            self::Monthly => 'Monthly',
        };
    }

    /** Days between occurrences. Monthly is approximated as 30 days. */
    public function intervalDays(): int
    {
        return match ($this) {
            self::Weekly => 7,
            self::Fortnightly => 14,
            self::Monthly => 30,
        };
    }

    /** Total span covered by a given number of occurrences, in days. */
    public function spanDays(int $occurrences): int
    {
        return $this->intervalDays() * max(0, $occurrences - 1);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
