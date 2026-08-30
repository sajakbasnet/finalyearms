<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function hasRole(UserRole|string $role): bool
    {
        $slug = $role instanceof UserRole ? $role->value : $role;

        return $this->role?->slug === $slug;
    }

    /** The assigned role, or null if the slug is not one we recognise. */
    public function userRole(): ?UserRole
    {
        return $this->role === null
            ? null
            : UserRole::tryFrom($this->role->slug);
    }

    public function isInstitutionAdmin(): bool
    {
        return $this->hasRole(UserRole::InstitutionAdmin);
    }

    public function isCoordinator(): bool
    {
        return $this->hasRole(UserRole::Coordinator);
    }

    public function isSupervisor(): bool
    {
        return $this->hasRole(UserRole::Supervisor);
    }

    public function isStudent(): bool
    {
        return $this->hasRole(UserRole::Student);
    }

    public function isEmployer(): bool
    {
        return $this->hasRole(UserRole::Employer);
    }

    /**
     * Whether the user leads the given group, or any group when none is given.
     *
     * Team Lead is contextual: it is read from group membership rather than
     * users.role_id, so a student can lead one group while remaining an
     * ordinary member of another.
     */
    public function leadsGroup(?StudentGroup $group = null): bool
    {
        $studentId = $this->student?->id;

        if ($studentId === null) {
            return false;
        }

        return StudentGroupMember::query()
            ->where('student_id', $studentId)
            ->where('is_leader', true)
            ->when($group !== null, fn ($query) => $query->where('student_group_id', $group->id))
            ->exists();
    }

    /**
     * Permission keys this user holds.
     *
     * Team Lead permissions are included only when a group is supplied and the
     * user leads it — they are group-scoped by construction and must never leak
     * into an unscoped check.
     *
     * @return list<string>
     */
    public function permissions(?StudentGroup $group = null): array
    {
        $permissions = $this->userRole()?->permissions() ?? [];

        if ($group !== null && $this->leadsGroup($group)) {
            $permissions = array_merge($permissions, UserRole::TeamLead->permissions());
        }

        return array_values(array_unique($permissions));
    }

    public function hasPermission(string $permission, ?StudentGroup $group = null): bool
    {
        return in_array($permission, $this->permissions($group), true);
    }
}
