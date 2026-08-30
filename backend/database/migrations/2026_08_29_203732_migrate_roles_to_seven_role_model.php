<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Moves the tenant role set from admin/teacher/student to the five assignable
 * roles of the access model.
 *
 *   admin   -> institution_admin
 *   teacher -> supervisor          (the schema already says supervisor_id)
 *   student -> student             (unchanged)
 *   + coordinator, employer        (new)
 *
 * Data-only: it renames rows in `roles`, so existing users keep working via
 * their unchanged role_id. platform_admin is absent by design (control-plane
 * identity) and team_lead is absent by design (contextual, from
 * student_group_members.is_leader).
 *
 * Existing `admin` users become Institution Admins. Coordinator duties
 * (templates, teams, supervisor overrides, proposal oversight) must be granted
 * by promoting specific users afterwards — this migration cannot know which.
 */
return new class extends Migration
{
    /** @var array<string, array{slug: string, name: string}> */
    private const RENAMES = [
        'admin' => ['slug' => 'institution_admin', 'name' => 'Institution Admin'],
        'teacher' => ['slug' => 'supervisor', 'name' => 'Supervisor'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            DB::table('roles')->where('slug', $from)->update([
                'slug' => $to['slug'],
                'name' => $to['name'],
                'description' => UserRole::from($to['slug'])->description(),
                'updated_at' => now(),
            ]);
        }

        foreach ([UserRole::Coordinator, UserRole::Employer] as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role->value],
                [
                    'name' => $role->label(),
                    'description' => $role->description(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        // Reversing drops the two new roles. Any user still holding them would
        // be orphaned, so refuse rather than silently break authentication.
        $inUse = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereIn('roles.slug', [UserRole::Coordinator->value, UserRole::Employer->value])
            ->count();

        if ($inUse > 0) {
            throw new RuntimeException(
                "Cannot roll back: {$inUse} user(s) hold the coordinator or employer role. Reassign them first."
            );
        }

        DB::table('roles')
            ->whereIn('slug', [UserRole::Coordinator->value, UserRole::Employer->value])
            ->delete();

        foreach (self::RENAMES as $from => $to) {
            DB::table('roles')->where('slug', $to['slug'])->update([
                'slug' => $from,
                'name' => ucfirst($from),
                'updated_at' => now(),
            ]);
        }
    }
};
