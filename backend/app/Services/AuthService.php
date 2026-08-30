<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuthEvent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class AuthService
{
    /** Failed attempts allowed per email+IP before the account is locked out. */
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    /**
     * A real bcrypt digest of a value nobody holds, compared against when the
     * account does not exist. Constant so no time is spent generating one.
     */
    private const TIMING_EQUALISER_HASH = '$2y$12$e0NRxNVBqZ1kX7Xn5eYQ0uKcQO7g0y9wYQ7bZ1nCq8m5oQ0Jp2W1S';

    public function __construct(
        private AuthAuditLogger $audit,
    ) {}

    /**
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password, string $deviceName = 'web'): array
    {
        $throttleKey = $this->throttleKey($email);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->audit->record(
                AuthEvent::LoginBlocked,
                email: $email,
                reason: 'Too many failed attempts',
                context: ['retry_after_seconds' => $seconds],
            );

            throw ValidationException::withMessages([
                'email' => ["Too many sign-in attempts. Try again in {$seconds} seconds."],
            ]);
        }

        $user = User::query()
            ->with(['role', 'teacher.department', 'student.department', 'student.academicSession'])
            ->where('email', $email)
            ->first();

        if ($user === null) {
            // Burn a comparable amount of time so a request for an unknown
            // address cannot be told from a known one by response latency.
            Hash::check($password, self::TIMING_EQUALISER_HASH);
            $passwordMatches = false;
        } else {
            $passwordMatches = Hash::check($password, $user->password);
        }

        if (! $passwordMatches) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            $this->audit->record(
                AuthEvent::LoginFailed,
                user: $user,
                email: $email,
                reason: $user === null ? 'No such account' : 'Incorrect password',
            );

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            $this->audit->record(
                AuthEvent::LoginFailed,
                user: $user,
                email: $email,
                reason: 'Account deactivated',
            );

            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Contact the administrator.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user->tokens()->where('name', $deviceName)->delete();
        $token = $user->createToken($deviceName)->plainTextToken;

        $this->audit->record(
            AuthEvent::LoginSucceeded,
            user: $user,
            email: $email,
            context: ['role' => $user->role?->slug, 'device' => $deviceName],
        );

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $accessToken = $user->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        } else {
            $user->tokens()->delete();
        }

        $this->audit->record(AuthEvent::Logout, user: $user, email: $user->email);
    }

    /**
     * @throws ValidationException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            $this->audit->record(
                AuthEvent::PasswordResetFailed,
                user: $user,
                email: $user->email,
                reason: 'Current password incorrect',
            );

            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $newPassword]);
        $user->tokens()->delete();

        $this->audit->record(AuthEvent::PasswordChanged, user: $user, email: $user->email);
    }

    /**
     * Sends a reset link.
     *
     * Always reports success to the caller. Revealing whether an address exists
     * would turn this into an account-enumeration oracle; the audit trail
     * records what actually happened.
     */
    public function sendPasswordResetLink(string $email): void
    {
        $status = Password::sendResetLink(['email' => $email]);

        $this->audit->record(
            $status === Password::RESET_LINK_SENT
                ? AuthEvent::PasswordResetRequested
                : AuthEvent::PasswordResetFailed,
            user: User::query()->where('email', $email)->first(),
            email: $email,
            reason: $status === Password::RESET_LINK_SENT ? null : (string) $status,
        );
    }

    /**
     * @throws ValidationException
     */
    public function resetPassword(string $email, string $token, string $password): void
    {
        $status = Password::reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ],
            function (User $user) use ($password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Every existing session is invalidated: a reset is the remedy
                // for a compromised account, so old tokens must not survive it.
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->audit->record(
                AuthEvent::PasswordResetFailed,
                email: $email,
                reason: (string) $status,
            );

            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        $this->audit->record(
            AuthEvent::PasswordResetCompleted,
            user: User::query()->where('email', $email)->first(),
            email: $email,
        );
    }

    public function loadProfile(User $user): User
    {
        return $user->load(['role', 'teacher.department', 'student.department', 'student.academicSession']);
    }

    private function throttleKey(string $email): string
    {
        return 'login:'.Str::lower($email).'|'.request()->ip();
    }
}
