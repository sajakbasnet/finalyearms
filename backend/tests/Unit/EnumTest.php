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
        $this->assertSame('Admin', UserRole::Admin->label());
        $this->assertSame('Teacher', UserRole::Teacher->label());
        $this->assertSame('Student', UserRole::Student->label());
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
