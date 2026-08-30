<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The seven roles in the SupervisEx access model.
 *
 * Two of them are not rows in a tenant's `roles` table:
 *
 * - PlatformAdmin is platform-wide and lives in the control-plane database
 *   (`super_admins`). A tenant database must never be able to grant it, or an
 *   institution admin could escalate to cross-tenant reach.
 * - TeamLead is contextual. `users.role_id` holds exactly one value and a team
 *   lead still submits their own work, so they remain a Student; leadership is
 *   read from `student_group_members.is_leader` per group. Its permissions are
 *   therefore always group-scoped.
 *
 * The remaining five are seeded into every tenant by TenantBootstrapSeeder.
 */
enum UserRole: string
{
    case PlatformAdmin = 'platform_admin';
    case InstitutionAdmin = 'institution_admin';
    case Coordinator = 'coordinator';
    case Supervisor = 'supervisor';
    case Student = 'student';
    case TeamLead = 'team_lead';
    case Employer = 'employer';

    public function label(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Platform Admin',
            self::InstitutionAdmin => 'Institution Admin',
            self::Coordinator => 'Coordinator',
            self::Supervisor => 'Supervisor',
            self::Student => 'Student',
            self::TeamLead => 'Team Lead',
            self::Employer => 'Employer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Tenant and platform management',
            self::InstitutionAdmin => 'Institution-level administration',
            self::Coordinator => 'Academic and project administration',
            self::Supervisor => 'Project supervision',
            self::Student => 'Project execution',
            self::TeamLead => 'Team and project coordination',
            self::Employer => 'Internship participation',
        };
    }

    /** Human-readable reach, mirrored from the access model. */
    public function accessLevel(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Platform-wide',
            self::InstitutionAdmin => 'Institution-wide',
            self::Coordinator => 'Institution/department',
            self::Supervisor => 'Assigned projects',
            self::Student => 'Own project/team',
            self::TeamLead => 'Own team/project',
            self::Employer => 'Assigned internship',
        };
    }

    /** Lives in the control-plane database, never in a tenant's roles table. */
    public function isPlatformScoped(): bool
    {
        return $this === self::PlatformAdmin;
    }

    /**
     * Derived from group membership rather than assigned via users.role_id.
     */
    public function isContextual(): bool
    {
        return $this === self::TeamLead;
    }

    /**
     * Roles seeded into a tenant and assignable to a user.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => ! $role->isPlatformScoped() && ! $role->isContextual(),
        ));
    }

    /**
     * Permission keys granted to this role, from config/rbac.php.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        /** @var list<string> $permissions */
        $permissions = config('rbac.roles.'.$this->value, []);

        return $permissions;
    }

    public function grants(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
