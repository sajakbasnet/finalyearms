<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of item an activity template can contain.
 *
 * Each type carries its own settings in `activity_template_items.config`, a
 * JSON column, rather than a sparse spread of nullable columns — one type's
 * fields are meaningless to the others, and institutions can extend a type
 * without a migration. `configRules()` is what keeps that column honest.
 */
enum ActivityItemType: string
{
    /** Plain deliverable with a due date. */
    case Task = 'task';

    /** Must be approved by someone before the project may continue. */
    case ApprovalGate = 'approval_gate';

    /** A milestone satisfied by holding a supervision meeting. */
    case MeetingMilestone = 'meeting_milestone';

    /** Repeats on a cadence — a weekly logbook entry, say. */
    case RecurringLog = 'recurring_log';

    public function label(): string
    {
        return match ($this) {
            self::Task => 'Task',
            self::ApprovalGate => 'Approval Gate',
            self::MeetingMilestone => 'Meeting Milestone',
            self::RecurringLog => 'Recurring Log',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Task => 'A deliverable the student submits by a due date.',
            self::ApprovalGate => 'Requires sign-off before later items may be started.',
            self::MeetingMilestone => 'Completed by holding and logging a supervision meeting.',
            self::RecurringLog => 'Repeats on a cadence for the life of the project.',
        };
    }

    /** Only recurring logs repeat; the rest occur once. */
    public function repeats(): bool
    {
        return $this === self::RecurringLog;
    }

    /**
     * Whether this type halts progression by default in a sequential template.
     *
     * An approval gate exists precisely to stop work until someone signs off,
     * so it defaults to blocking. Everything else defaults to not blocking, and
     * a coordinator can override either way per item.
     */
    public function blocksByDefault(): bool
    {
        return $this === self::ApprovalGate;
    }

    /**
     * Validation rules for this type's `config` payload, keyed without the
     * `config.` prefix — callers namespace them.
     *
     * @return array<string, mixed>
     */
    public function configRules(): array
    {
        return match ($this) {
            self::Task => [],

            self::ApprovalGate => [
                // Which role signs off. Restricted to roles that actually
                // review work — a student cannot approve their own gate.
                'approver_role' => [
                    'required',
                    'string',
                    'in:'.implode(',', [
                        UserRole::Supervisor->value,
                        UserRole::Coordinator->value,
                        UserRole::InstitutionAdmin->value,
                    ]),
                ],
            ],

            self::MeetingMilestone => [
                'minimum_meetings' => ['nullable', 'integer', 'min:1', 'max:20'],
                'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            ],

            self::RecurringLog => [
                'cadence' => ['required', 'string', 'in:'.implode(',', RecurringCadence::values())],
                // Bounded so a template cannot generate an unusable number of
                // entries when it is applied to a project.
                'occurrences' => ['required', 'integer', 'min:1', 'max:60'],
            ],
        };
    }

    /**
     * Config keys this type recognises. Anything else is rejected, so a typo
     * fails loudly instead of being silently stored and ignored.
     *
     * @return list<string>
     */
    public function configKeys(): array
    {
        return array_keys($this->configRules());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
