<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Student;
use App\Models\User;
use App\Services\AuthService;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_auth_service_login_returns_token_and_user(): void
    {
        $result = app(AuthService::class)->login('teacher@fyp.local', 'password');

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertSame('teacher@fyp.local', $result['user']->email);
        $this->assertNotEmpty($result['token']);
    }

    public function test_auth_service_rejects_bad_password(): void
    {
        $this->expectException(ValidationException::class);

        app(AuthService::class)->login('teacher@fyp.local', 'bad-password');
    }

    public function test_student_service_resolves_student_profile(): void
    {
        $user = User::query()->where('email', 'student@fyp.local')->firstOrFail();
        $student = app(StudentService::class)->resolveStudent($user);

        $this->assertInstanceOf(Student::class, $student);
        $this->assertSame('CS-2022-001', $student->registration_number);
        $this->assertNotNull($student->supervisorAssignment);
    }

    public function test_student_service_rejects_non_student_user(): void
    {
        $this->expectException(ValidationException::class);

        $admin = User::query()->where('email', 'admin@fyp.local')->firstOrFail();
        app(StudentService::class)->resolveStudent($admin);
    }

    public function test_student_service_overview_includes_supervisor(): void
    {
        $user = User::query()->where('email', 'student@fyp.local')->firstOrFail();
        $service = app(StudentService::class);
        $overview = $service->overview($service->resolveStudent($user));

        $this->assertSame('teacher@fyp.local', $overview['supervisor']['email']);
        $this->assertSame('Smart Campus Attendance System', $overview['project']['title']);
    }

    public function test_student_service_timeline_returns_milestones(): void
    {
        $user = User::query()->where('email', 'student@fyp.local')->firstOrFail();
        $service = app(StudentService::class);
        $timeline = $service->timeline($service->resolveStudent($user));

        $this->assertNotEmpty($timeline);
        $this->assertSame('Proposal Submitted', $timeline[0]['title']);
    }

    public function test_user_role_helpers(): void
    {
        $student = User::query()->where('email', 'student@fyp.local')->firstOrFail()->load('role');
        $teacher = User::query()->where('email', 'teacher@fyp.local')->firstOrFail()->load('role');
        $admin = User::query()->where('email', 'admin@fyp.local')->firstOrFail()->load('role');

        $this->assertTrue($student->isStudent());
        $this->assertFalse($student->isTeacher());
        $this->assertTrue($teacher->isTeacher());
        $this->assertTrue($admin->isAdmin());
    }
}
