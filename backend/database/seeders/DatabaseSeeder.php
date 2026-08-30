<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProposalComment;
use App\Models\ProposalVersion;
use App\Models\Role;
use App\Models\Student;
use App\Models\SupervisorAssignment;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\PortalNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles come from the shared bootstrap so demo data cannot drift from
        // what a real tenant is provisioned with.
        $this->call(TenantBootstrapSeeder::class);

        $adminRole = Role::query()->where('slug', UserRole::InstitutionAdmin->value)->firstOrFail();
        $coordinatorRole = Role::query()->where('slug', UserRole::Coordinator->value)->firstOrFail();
        $teacherRole = Role::query()->where('slug', UserRole::Supervisor->value)->firstOrFail();
        $studentRole = Role::query()->where('slug', UserRole::Student->value)->firstOrFail();

        $cs = Department::query()->create([
            'name' => 'Computer Science',
            'code' => 'CS',
            'description' => 'Department of Computer Science',
        ]);

        $se = Department::query()->create([
            'name' => 'Software Engineering',
            'code' => 'SE',
            'description' => 'Department of Software Engineering',
        ]);

        $csBatch = Batch::query()->create([
            'department_id' => $cs->id,
            'name' => '2022 Intake',
            'intake_year' => 2022,
        ]);

        $seBatch = Batch::query()->create([
            'department_id' => $se->id,
            'name' => '2022 Intake',
            'intake_year' => 2022,
        ]);

        $session = AcademicSession::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        User::query()->create([
            'role_id' => $adminRole->id,
            'name' => 'System Admin',
            'email' => 'admin@fyp.local',
            'phone' => '9800000001',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        User::query()->create([
            'role_id' => $coordinatorRole->id,
            'name' => 'Programme Coordinator',
            'email' => 'coordinator@fyp.local',
            'phone' => '9800000005',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $teacherUser = User::query()->create([
            'role_id' => $teacherRole->id,
            'name' => 'Dr. Sarah Sharma',
            'email' => 'teacher@fyp.local',
            'phone' => '9800000002',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $teacher = Teacher::query()->create([
            'user_id' => $teacherUser->id,
            'department_id' => $cs->id,
            'employee_id' => 'EMP-001',
            'designation' => 'Associate Professor',
            'max_projects' => 8,
        ]);

        $teacherUser2 = User::query()->create([
            'role_id' => $teacherRole->id,
            'name' => 'Prof. Raj Thapa',
            'email' => 'teacher2@fyp.local',
            'phone' => '9800000003',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Teacher::query()->create([
            'user_id' => $teacherUser2->id,
            'department_id' => $se->id,
            'employee_id' => 'EMP-002',
            'designation' => 'Assistant Professor',
            'max_projects' => 6,
        ]);

        $studentUser = User::query()->create([
            'role_id' => $studentRole->id,
            'name' => 'Aarav Poudel',
            'email' => 'student@fyp.local',
            'phone' => '9800000004',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'department_id' => $cs->id,
            'academic_session_id' => $session->id,
            'registration_number' => 'CS-2022-001',
            'roll_number' => '22CS001',
            'batch_id' => $csBatch->id,
        ]);

        $studentUser2 = User::query()->create([
            'role_id' => $studentRole->id,
            'name' => 'Nisha Karki',
            'email' => 'student2@fyp.local',
            'phone' => '9800000005',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Student::query()->create([
            'user_id' => $studentUser2->id,
            'department_id' => $se->id,
            'academic_session_id' => $session->id,
            'registration_number' => 'SE-2022-014',
            'roll_number' => '22SE014',
            'batch_id' => $seBatch->id,
        ]);

        SupervisorAssignment::query()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'academic_session_id' => $session->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        $project = Project::query()->create([
            'title' => 'Smart Campus Attendance System',
            'description' => 'An AI-assisted attendance system using facial recognition for campus labs.',
            'domain' => 'Computer Vision',
            'technology_stack' => 'Laravel, React, Python, OpenCV',
            'category' => 'Individual',
            'status' => ProjectStatus::ProposalPending,
            'supervisor_id' => $teacher->id,
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
        ]);

        $proposal = ProposalVersion::query()->create([
            'project_id' => $project->id,
            'version_number' => 1,
            'title' => 'Smart Campus Attendance System',
            'abstract' => 'This project proposes a facial recognition based attendance system for university labs.',
            'background' => 'Many labs still use paper attendance.',
            'problem_statement' => 'Manual attendance wastes class time and is prone to proxy marking.',
            'objectives' => 'Automate attendance marking and generate department-level reports.',
            'scope' => 'Department labs in phase 1.',
            'methodology' => 'Capture frames, detect faces, match embeddings, store records in a centralized database.',
            'literature_review' => 'RFID and QR systems exist; face recognition reduces friction.',
            'timeline' => 'Proposal → Design → Development → Testing → Report',
            'expected_outcome' => 'A working attendance module integrated with the portal.',
            'technologies' => 'Laravel, React, Python, OpenCV',
            'references' => 'OpenCV documentation',
            'status' => ProposalStatus::RevisionRequested,
            'submitted_by' => $studentUser->id,
            'submitted_at' => now()->subDays(3),
        ]);

        ProposalComment::query()->create([
            'proposal_version_id' => $proposal->id,
            'user_id' => $teacherUser->id,
            'section' => 'methodology',
            'comment' => 'Please explain how privacy and consent will be handled for facial data.',
            'action' => 'request_revision',
        ]);

        $titles = [
            'Proposal Submitted',
            'Proposal Approved',
            'Requirement Analysis',
            'Design',
            'Development',
            'Testing',
            'Final Report',
            'Presentation',
        ];

        foreach ($titles as $index => $title) {
            Milestone::query()->create([
                'project_id' => $project->id,
                'title' => $title,
                'due_date' => now()->addWeeks($index + 1)->toDateString(),
                'status' => $index === 0 ? MilestoneStatus::Completed : MilestoneStatus::Pending,
                'sort_order' => $index + 1,
            ]);
        }

        $studentUser->notify(new PortalNotification([
            'title' => 'Revision requested',
            'message' => 'Dr. Sarah Sharma requested revisions on your proposal.',
            'type' => 'revision_requested',
            'proposal_id' => $proposal->id,
            'project_id' => $project->id,
        ]));

        $studentUser->notify(new PortalNotification([
            'title' => 'Supervisor assigned',
            'message' => 'You have been assigned to Dr. Sarah Sharma.',
            'type' => 'supervisor_assigned',
            'project_id' => $project->id,
        ]));
    }
}
