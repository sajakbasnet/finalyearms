<?php

declare(strict_types=1);

use App\Enums\UserRole;

/*
 * Permission catalogue and role matrix.
 *
 * Single source of truth for API authorization: AuthServiceProvider defines one
 * Gate per key below, and routes are guarded with `can:<key>`. Adding a
 * permission here and referencing it from a route is all that is needed — there
 * is no second place to register it.
 *
 * Naming: <resource>.<action>, with an `.own` / `.assigned` / `.any` suffix
 * where reach matters. A bare key means the role may act across the tenant.
 *
 * Scoped permissions (those ending .own / .assigned, and every team.* key) are
 * necessary but not sufficient: the Gate also receives the subject and checks
 * ownership. Holding `proposal.review` does not let a supervisor review a
 * proposal that isn't theirs.
 */
return [

    /*
     |----------------------------------------------------------------------
     | Catalogue — every permission, with what it means.
     |----------------------------------------------------------------------
     */
    'permissions' => [

        // Institution structure
        'department.view' => 'List departments',
        'department.manage' => 'Create, update and delete departments',
        'session.view' => 'List academic sessions',
        'session.manage' => 'Create, update and delete academic sessions, and set the active one',
        'session-date.manage' => 'Add, edit and remove key dates within a session',
        'batch.view' => 'List student batches',
        'batch.manage' => 'Create, update and retire student batches',

        // Accounts
        'user.view' => 'List institution users',
        'user.manage' => 'Create, update and deactivate users',

        // Templates — Coordinator's month-1 focus
        'project-type.view' => 'List project types',
        'project-type.manage' => 'Create, update and delete project types',
        'activity-template.view' => 'List activity templates',
        'activity-template.manage' => 'Create, update and archive activity templates',
        'activity-template.publish' => 'Publish a draft template, making it usable and immutable',

        // Teams
        'team.view' => 'List student groups across the institution',
        'team.manage' => 'Create and dissolve student groups, set supervisors',
        'team.member.manage' => 'Add or remove members of a team you lead',
        'team.act' => 'Take shared project actions on behalf of a team you lead',
        'project.create.own' => 'Start an individual or team project',
        'team.manage.own' => 'Invite, remove and hand over the lead on your own team',
        'team.invitation.respond' => 'Accept or decline an invitation sent to you',
        'supervisor.directory.view' => 'Browse supervisors you could approach',
        'supervisor-request.send' => 'Ask a supervisor to take your project on',
        'supervisor-request.respond' => 'Accept or decline a request sent to you',

        // Supervision
        'supervisor-assignment.manage' => 'Assign or override a student supervisor',

        // Proposals
        'proposal.view.any' => 'View any proposal in the institution',
        'proposal.view.assigned' => 'View proposals for supervised projects',
        'proposal.view.own' => 'View your own proposal',
        'proposal.submit' => 'Create, edit and submit your own proposal',
        'proposal.review' => 'Approve, reject or request revision on a proposal',
        'proposal.comment' => 'Comment on a proposal under review',
        'proposal.reply' => 'Reply to a proposal comment',

        // Progress and delivery
        'progress.view.assigned' => 'View progress reports for supervised projects',
        'progress.submit' => 'Submit a progress report for your own project',
        'progress.review' => 'Review and comment on a progress report',
        'milestone.manage' => 'Update milestone status for a supervised project',
        'timeline.view.own' => 'View your own project timeline',
        'final-submission.submit' => 'Upload your final submission',

        // Assessment
        'evaluation.manage' => 'Record or amend an evaluation for a supervised project',
        'meeting.log' => 'Record a supervision meeting',
        'project.view.any' => 'View any project in the institution',
        'project.view.assigned' => 'View supervised projects',
        'project.file.download' => 'Download files attached to a supervised project',

        // Internships — role and permissions exist; no feature surface yet.
        'internship.view.assigned' => 'View an internship you are attached to',
        'internship.evaluate' => 'Record an employer-side internship evaluation',

        // Tenant presentation
        'branding.view' => 'View institution branding',
        'branding.manage' => 'Update institution branding',

        /*
         | Gate for the whole /student workspace. Every role can read its own
         | notifications, so guarding those routes on notification.view.own
         | alone let an Institution Admin into student-scoped endpoints that
         | resolve a Student profile. This key is Student-only and guards the
         | prefix; the finer keys still guard individual actions.
         */
        'student.workspace' => 'Access the student workspace',

        // Own account
        'notification.view.own' => 'View your own notifications',
        'profile.view.own' => 'View your own profile',
    ],

    /*
     |----------------------------------------------------------------------
     | Role matrix
     |----------------------------------------------------------------------
     |
     | PlatformAdmin is deliberately absent: it is a control-plane identity
     | (`super_admins`) and holds no permissions inside a tenant.
     |
     */
    'roles' => [

        UserRole::InstitutionAdmin->value => [
            'department.view',
            'department.manage',
            'session.view',
            'session.manage',
            'session-date.manage',
            'batch.view',
            'batch.manage',
            'user.view',
            'user.manage',
            'team.view',
            'project.view.any',
            'proposal.view.any',
            'branding.view',
            'branding.manage',
            'notification.view.own',
            'profile.view.own',
        ],

        UserRole::Coordinator->value => [
            'department.view',
            'session.view',
            'batch.view',
            'user.view',
            'project-type.view',
            'project-type.manage',
            'activity-template.view',
            'activity-template.manage',
            'activity-template.publish',
            'team.view',
            'team.manage',
            'supervisor-assignment.manage',
            'proposal.view.any',
            'project.view.any',
            'branding.view',
            'notification.view.own',
            'profile.view.own',
        ],

        UserRole::Supervisor->value => [
            'department.view',
            'session.view',
            'batch.view',
            'team.view',
            'supervisor-request.respond',
            'proposal.view.assigned',
            'proposal.review',
            'proposal.comment',
            'proposal.reply',
            'progress.view.assigned',
            'progress.review',
            'milestone.manage',
            'evaluation.manage',
            'meeting.log',
            'project.view.assigned',
            'project.file.download',
            'branding.view',
            'notification.view.own',
            'profile.view.own',
        ],

        UserRole::Student->value => [
            'student.workspace',
            'project.create.own',
            'team.manage.own',
            'team.invitation.respond',
            'supervisor.directory.view',
            'supervisor-request.send',
            'proposal.view.own',
            'proposal.submit',
            'proposal.reply',
            'progress.submit',
            'timeline.view.own',
            'final-submission.submit',
            'branding.view',
            'notification.view.own',
            'profile.view.own',
        ],

        /*
         | Contextual. Composed on top of Student when the user leads the group
         | being acted on — never assigned via users.role_id. Every key here is
         | group-scoped and the Gate re-checks is_leader against the subject.
         */
        UserRole::TeamLead->value => [
            'team.member.manage',
            'team.act',
        ],

        UserRole::Employer->value => [
            'internship.view.assigned',
            'internship.evaluate',
            'branding.view',
            'notification.view.own',
            'profile.view.own',
        ],
    ],

];
