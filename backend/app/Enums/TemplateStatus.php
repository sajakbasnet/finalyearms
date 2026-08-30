<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of an activity template.
 *
 * Published versions are immutable. Editing one forks a new draft at the next
 * version number instead of mutating in place — otherwise changing a template
 * would silently rewrite the plan of every project that had already adopted it.
 */
enum TemplateStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Whether the template and its items may still be edited in place. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** Whether it can be applied to a project. */
    public function isUsable(): bool
    {
        return $this === self::Published;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
