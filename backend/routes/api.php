<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\AcademicSessionDateController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\BatchController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\Coordinator\ActivityTemplateController;
use App\Http\Controllers\Api\Coordinator\ProjectTypeController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Student\StudentController;
use App\Http\Controllers\Api\Student\SupervisorRequestController as StudentSupervisorRequestController;
use App\Http\Controllers\Api\Student\TeamController;
use App\Http\Controllers\Api\Teacher\SupervisorRequestController as TeacherSupervisorRequestController;
use App\Http\Controllers\Api\Teacher\TeacherController;
use Illuminate\Support\Facades\Route;

/*
 * Authorization is permission-based, not role-based: routes are guarded with
 * `can:<key>` and the matrix lives in config/rbac.php. Adding a role, or moving
 * a capability between roles, is a config change — no route edits.
 */

/*
 * Unauthenticated: the SPA fetches this before sign-in so the login screen
 * renders in the institution's own branding. Throttled because it is the only
 * public read endpoint.
 */
Route::get('/branding', [BrandingController::class, 'show'])->middleware('throttle:60,1');

Route::prefix('auth')->group(function (): void {
    // Tighter throttles than the API default: these are the endpoints worth
    // brute-forcing. AuthService additionally locks out per email+IP.
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/dashboard', DashboardController::class);

    /*
     |----------------------------------------------------------------------
     | Institution Admin — departments, calendar, batches, users
     |----------------------------------------------------------------------
     */
    Route::prefix('admin')->group(function (): void {
        Route::get('/departments', [AdminController::class, 'departments'])->middleware('can:department.view');
        Route::post('/departments', [AdminController::class, 'storeDepartment'])->middleware('can:department.manage');
        Route::put('/departments/{department}', [AdminController::class, 'updateDepartment'])->middleware('can:department.manage');
        Route::delete('/departments/{department}', [AdminController::class, 'destroyDepartment'])->middleware('can:department.manage');

        Route::get('/sessions', [AdminController::class, 'sessions'])->middleware('can:session.view');
        Route::post('/sessions', [AdminController::class, 'storeSession'])->middleware('can:session.manage');
        Route::put('/sessions/{session}', [AdminController::class, 'updateSession'])->middleware('can:session.manage');
        Route::delete('/sessions/{session}', [AdminController::class, 'destroySession'])->middleware('can:session.manage');
        // Exactly one session is active at a time; this stands the others down.
        Route::post('/sessions/{session}/activate', [AdminController::class, 'activateSession'])->middleware('can:session.manage');

        // Key dates, nested because a date has no meaning outside its session.
        Route::get('/sessions/{session}/dates', [AcademicSessionDateController::class, 'index'])->middleware('can:session.view');
        Route::post('/sessions/{session}/dates', [AcademicSessionDateController::class, 'store'])->middleware('can:session-date.manage');
        Route::put('/sessions/{session}/dates/{date}', [AcademicSessionDateController::class, 'update'])->middleware('can:session-date.manage');
        Route::delete('/sessions/{session}/dates/{date}', [AcademicSessionDateController::class, 'destroy'])->middleware('can:session-date.manage');

        Route::get('/batches', [BatchController::class, 'index'])->middleware('can:batch.view');
        Route::post('/batches', [BatchController::class, 'store'])->middleware('can:batch.manage');
        Route::put('/batches/{batch}', [BatchController::class, 'update'])->middleware('can:batch.manage');
        Route::delete('/batches/{batch}', [BatchController::class, 'destroy'])->middleware('can:batch.manage');

        Route::get('/teachers', [AdminController::class, 'teachers'])->middleware('can:user.view');
        Route::post('/teachers', [AdminController::class, 'storeTeacher'])->middleware('can:user.manage');
        Route::put('/teachers/{teacher}', [AdminController::class, 'updateTeacher'])->middleware('can:user.manage');
        Route::delete('/teachers/{teacher}', [AdminController::class, 'destroyTeacher'])->middleware('can:user.manage');

        Route::get('/students', [AdminController::class, 'students'])->middleware('can:user.view');
        Route::post('/students', [AdminController::class, 'storeStudent'])->middleware('can:user.manage');
        Route::put('/students/{student}', [AdminController::class, 'updateStudent'])->middleware('can:user.manage');
        Route::delete('/students/{student}', [AdminController::class, 'destroyStudent'])->middleware('can:user.manage');

        // Coordinator territory: supervisor overrides and proposal oversight.
        Route::post('/students/{student}/assign-supervisor', [AdminController::class, 'assignSupervisor'])
            ->middleware('can:supervisor-assignment.manage');
        Route::get('/proposals', [AdminController::class, 'proposals'])->middleware('can:proposal.view.any');

        // POST rather than PUT: PHP does not parse multipart bodies on PUT,
        // and this carries the logo/favicon uploads.
        Route::post('/branding', [BrandingController::class, 'update'])->middleware('can:branding.manage');
    });

    /*
     |----------------------------------------------------------------------
     | Coordinator — templates
     |----------------------------------------------------------------------
     */
    Route::prefix('coordinator')->group(function (): void {
        Route::get('/project-types', [ProjectTypeController::class, 'index'])->middleware('can:project-type.view');
        Route::post('/project-types', [ProjectTypeController::class, 'store'])->middleware('can:project-type.manage');
        Route::put('/project-types/{projectType}', [ProjectTypeController::class, 'update'])->middleware('can:project-type.manage');
        Route::delete('/project-types/{projectType}', [ProjectTypeController::class, 'destroy'])->middleware('can:project-type.manage');

        // Item types and cadences, so a builder UI does not hard-code them.
        Route::get('/activity-templates/item-types', [ActivityTemplateController::class, 'itemTypes'])->middleware('can:activity-template.view');

        Route::get('/activity-templates', [ActivityTemplateController::class, 'index'])->middleware('can:activity-template.view');
        Route::get('/activity-templates/{activityTemplate}', [ActivityTemplateController::class, 'show'])->middleware('can:activity-template.view');
        Route::post('/activity-templates', [ActivityTemplateController::class, 'store'])->middleware('can:activity-template.manage');
        Route::put('/activity-templates/{activityTemplate}', [ActivityTemplateController::class, 'update'])->middleware('can:activity-template.manage');
        Route::delete('/activity-templates/{activityTemplate}', [ActivityTemplateController::class, 'destroy'])->middleware('can:activity-template.manage');

        // Publishing freezes a draft; versioning opens the next one.
        Route::post('/activity-templates/{activityTemplate}/publish', [ActivityTemplateController::class, 'publish'])->middleware('can:activity-template.publish');
        Route::post('/activity-templates/{activityTemplate}/versions', [ActivityTemplateController::class, 'newVersion'])->middleware('can:activity-template.manage');
        Route::post('/activity-templates/{activityTemplate}/clone', [ActivityTemplateController::class, 'clone'])->middleware('can:activity-template.manage');
    });

    /*
     |----------------------------------------------------------------------
     | Supervisor — assigned projects
     |----------------------------------------------------------------------
     */
    Route::prefix('teacher')->group(function (): void {
        Route::get('/students', [TeacherController::class, 'students'])->middleware('can:project.view.assigned');
        Route::get('/proposals', [TeacherController::class, 'proposals'])->middleware('can:proposal.view.assigned');
        Route::get('/proposals/{proposal}', [TeacherController::class, 'showProposal'])->middleware('can:proposal.view.assigned');
        Route::post('/proposals/{proposal}/review', [TeacherController::class, 'reviewProposal'])->middleware('can:proposal.review');
        Route::post('/proposals/{proposal}/comments', [TeacherController::class, 'commentOnProposal'])->middleware('can:proposal.comment');
        Route::post('/comments/{comment}/replies', [TeacherController::class, 'replyToComment'])->middleware('can:proposal.reply');

        // Requests awaiting this supervisor's decision.
        Route::get('/supervisor-requests', [TeacherSupervisorRequestController::class, 'index'])->middleware('can:supervisor-request.respond');
        Route::post('/supervisor-requests/{supervisorRequest}/accept', [TeacherSupervisorRequestController::class, 'accept'])->middleware('can:supervisor-request.respond');
        Route::post('/supervisor-requests/{supervisorRequest}/decline', [TeacherSupervisorRequestController::class, 'decline'])->middleware('can:supervisor-request.respond');

        Route::get('/progress-reports', [TeacherController::class, 'progressReports'])->middleware('can:progress.view.assigned');
        Route::post('/progress-reports/{progressReport}/review', [TeacherController::class, 'reviewProgress'])->middleware('can:progress.review');

        Route::get('/projects/{project}', [TeacherController::class, 'showProject'])->middleware('can:project.view.assigned');
        Route::patch('/milestones/{milestone}', [TeacherController::class, 'updateMilestone'])->middleware('can:milestone.manage');
        Route::post('/projects/{project}/evaluation', [TeacherController::class, 'saveEvaluation'])->middleware('can:evaluation.manage');
        Route::get('/projects/{project}/download', [TeacherController::class, 'downloadFile'])->middleware('can:project.file.download');
    });

    /*
     |----------------------------------------------------------------------
     | Student — own project/team
     |----------------------------------------------------------------------
     */
    // The prefix guard is Student-only. Individual routes still carry their own
    // finer permission, but this stops a role that merely holds a shared key
    // like notification.view.own from reaching student-scoped endpoints.
    Route::prefix('student')->middleware('can:student.workspace')->group(function (): void {
        Route::get('/overview', [StudentController::class, 'overview'])->middleware('can:proposal.view.own');
        Route::get('/workspace', [StudentController::class, 'workspace'])->middleware('can:proposal.view.own');
        Route::post('/proposals', [StudentController::class, 'saveProposal'])->middleware('can:proposal.submit');
        Route::post('/proposals/submit', [StudentController::class, 'submitProposal'])->middleware('can:proposal.submit');
        Route::post('/comments/{comment}/replies', [StudentController::class, 'replyToComment'])->middleware('can:proposal.reply');
        Route::post('/progress-reports', [StudentController::class, 'storeProgress'])->middleware('can:progress.submit');
        Route::post('/final-submission', [StudentController::class, 'uploadFinal'])->middleware('can:final-submission.submit');
        Route::get('/timeline', [StudentController::class, 'timeline'])->middleware('can:timeline.view.own');
        Route::get('/proposals/history', [StudentController::class, 'proposalHistory'])->middleware('can:proposal.view.own');

        // Team formation. A project exists before it has a supervisor.
        Route::post('/projects', [TeamController::class, 'storeProject'])->middleware('can:project.create.own');
        Route::get('/team', [TeamController::class, 'show'])->middleware('can:proposal.view.own');
        Route::post('/team/invitations', [TeamController::class, 'invite'])->middleware('can:team.manage.own');
        Route::delete('/team/members/{member}', [TeamController::class, 'removeMember'])->middleware('can:team.manage.own');
        Route::post('/team/lead/{member}', [TeamController::class, 'transferLead'])->middleware('can:team.manage.own');

        Route::get('/invitations', [TeamController::class, 'invitations'])->middleware('can:team.invitation.respond');
        Route::post('/invitations/{invitation}/accept', [TeamController::class, 'acceptInvitation'])->middleware('can:team.invitation.respond');
        Route::post('/invitations/{invitation}/decline', [TeamController::class, 'declineInvitation'])->middleware('can:team.invitation.respond');

        // Supervisor directory and requests.
        Route::get('/supervisors', [StudentSupervisorRequestController::class, 'directory'])->middleware('can:supervisor.directory.view');
        Route::get('/supervisor-requests', [StudentSupervisorRequestController::class, 'index'])->middleware('can:supervisor-request.send');
        Route::post('/supervisor-requests', [StudentSupervisorRequestController::class, 'store'])->middleware('can:supervisor-request.send');
        Route::post('/supervisor-requests/{supervisorRequest}/withdraw', [StudentSupervisorRequestController::class, 'withdraw'])->middleware('can:supervisor-request.send');

        Route::get('/notifications', [StudentController::class, 'notifications'])->middleware('can:notification.view.own');
        Route::post('/notifications/read-all', [StudentController::class, 'markAllNotificationsRead'])->middleware('can:notification.view.own');
        Route::post('/notifications/{notification}/read', [StudentController::class, 'markNotificationRead'])->middleware('can:notification.view.own');
    });
});
