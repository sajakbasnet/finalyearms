<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Student\StudentController;
use App\Http\Controllers\Api\Teacher\TeacherController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/dashboard', DashboardController::class);

    Route::prefix('admin')->middleware('role:admin')->group(function (): void {
        Route::get('/departments', [AdminController::class, 'departments']);
        Route::post('/departments', [AdminController::class, 'storeDepartment']);
        Route::put('/departments/{department}', [AdminController::class, 'updateDepartment']);
        Route::delete('/departments/{department}', [AdminController::class, 'destroyDepartment']);

        Route::get('/sessions', [AdminController::class, 'sessions']);
        Route::post('/sessions', [AdminController::class, 'storeSession']);

        Route::get('/teachers', [AdminController::class, 'teachers']);
        Route::post('/teachers', [AdminController::class, 'storeTeacher']);
        Route::put('/teachers/{teacher}', [AdminController::class, 'updateTeacher']);
        Route::delete('/teachers/{teacher}', [AdminController::class, 'destroyTeacher']);

        Route::get('/students', [AdminController::class, 'students']);
        Route::post('/students', [AdminController::class, 'storeStudent']);
        Route::put('/students/{student}', [AdminController::class, 'updateStudent']);
        Route::delete('/students/{student}', [AdminController::class, 'destroyStudent']);
        Route::post('/students/{student}/assign-supervisor', [AdminController::class, 'assignSupervisor']);

        Route::get('/proposals', [AdminController::class, 'proposals']);
    });

    Route::prefix('teacher')->middleware('role:teacher')->group(function (): void {
        Route::get('/students', [TeacherController::class, 'students']);
        Route::get('/proposals', [TeacherController::class, 'proposals']);
        Route::get('/proposals/{proposal}', [TeacherController::class, 'showProposal']);
        Route::post('/proposals/{proposal}/review', [TeacherController::class, 'reviewProposal']);
        Route::post('/proposals/{proposal}/comments', [TeacherController::class, 'commentOnProposal']);
        Route::post('/comments/{comment}/replies', [TeacherController::class, 'replyToComment']);

        Route::get('/progress-reports', [TeacherController::class, 'progressReports']);
        Route::post('/progress-reports/{progressReport}/review', [TeacherController::class, 'reviewProgress']);

        Route::get('/projects/{project}', [TeacherController::class, 'showProject']);
        Route::patch('/milestones/{milestone}', [TeacherController::class, 'updateMilestone']);
        Route::post('/projects/{project}/evaluation', [TeacherController::class, 'saveEvaluation']);
        Route::get('/projects/{project}/download', [TeacherController::class, 'downloadFile']);
    });

    Route::prefix('student')->middleware('role:student')->group(function (): void {
        Route::get('/overview', [StudentController::class, 'overview']);
        Route::get('/workspace', [StudentController::class, 'workspace']);
        Route::post('/proposals', [StudentController::class, 'saveProposal']);
        Route::post('/proposals/submit', [StudentController::class, 'submitProposal']);
        Route::post('/comments/{comment}/replies', [StudentController::class, 'replyToComment']);
        Route::post('/progress-reports', [StudentController::class, 'storeProgress']);
        Route::post('/final-submission', [StudentController::class, 'uploadFinal']);
        Route::get('/timeline', [StudentController::class, 'timeline']);
        Route::get('/notifications', [StudentController::class, 'notifications']);
        Route::post('/notifications/read-all', [StudentController::class, 'markAllNotificationsRead']);
        Route::post('/notifications/{notification}/read', [StudentController::class, 'markNotificationRead']);
    });
});
