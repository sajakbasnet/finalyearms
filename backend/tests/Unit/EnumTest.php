<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EnumTest extends TestCase
{
    public function test_user_role_labels(): void
    {
        $this->assertSame('Platform Admin', UserRole::PlatformAdmin->label());
        $this->assertSame('Institution Admin', UserRole::InstitutionAdmin->label());
        $this->assertSame('Coordinator', UserRole::Coordinator->label());
        $this->assertSame('Supervisor', UserRole::Supervisor->label());
        $this->assertSame('Student', UserRole::Student->label());
        $this->assertSame('Team Lead', UserRole::TeamLead->label());
        $this->assertSame('Employer', UserRole::Employer->label());
    }

    public function test_assignable_roles_exclude_platform_and_contextual_roles(): void
    {
        $assignable = array_map(
            fn (UserRole $role): string => $role->value,
            UserRole::assignable(),
        );

        // platform_admin is a control-plane identity; team_lead is derived from
        // student_group_members.is_leader. Neither belongs in a tenant's roles
        // table, or an institution admin could grant cross-tenant reach.
        $this->assertNotContains('platform_admin', $assignable);
        $this->assertNotContains('team_lead', $assignable);

        $this->assertSame([
            'institution_admin',
            'coordinator',
            'supervisor',
            'student',
            'employer',
        ], $assignable);
    }

    public function test_platform_and_contextual_flags(): void
    {
        $this->assertTrue(UserRole::PlatformAdmin->isPlatformScoped());
        $this->assertTrue(UserRole::TeamLead->isContextual());
        $this->assertFalse(UserRole::Coordinator->isPlatformScoped());
        $this->assertFalse(UserRole::Student->isContextual());
    }

    #[DataProvider('proposalStatusProvider')]
    public function test_proposal_status_labels(ProposalStatus $status, string $label): void
    {
        $this->assertSame($label, $status->label());
        $this->assertNotEmpty($status->value);
    }

    /**
     * @return array<string, array{0: ProposalStatus, 1: string}>
     */
    public static function proposalStatusProvider(): array
    {
        return [
            'draft' => [ProposalStatus::Draft, 'Draft'],
            'submitted' => [ProposalStatus::Submitted, 'Submitted'],
            'under_review' => [ProposalStatus::UnderReview, 'Under Review'],
            'revision_requested' => [ProposalStatus::RevisionRequested, 'Revision Requested'],
            'resubmitted' => [ProposalStatus::Resubmitted, 'Resubmitted'],
            'approved' => [ProposalStatus::Approved, 'Approved'],
            'rejected' => [ProposalStatus::Rejected, 'Rejected'],
            'cancelled' => [ProposalStatus::Cancelled, 'Cancelled'],
        ];
    }

    public function test_project_and_milestone_status_values(): void
    {
        $this->assertSame('proposal_pending', ProjectStatus::ProposalPending->value);
        $this->assertSame('In Progress', ProjectStatus::InProgress->label());
        $this->assertSame('completed', MilestoneStatus::Completed->value);
        $this->assertSame('Pending', MilestoneStatus::Pending->label());
    }
}
