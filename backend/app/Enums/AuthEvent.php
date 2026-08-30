<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Authentication events recorded in `auth_audit_logs`.
 *
 * Deliberately covers failures and lockouts, not just successes — an audit
 * trail that only records what worked cannot answer the questions it exists
 * for.
 */
enum AuthEvent: string
{
    case LoginSucceeded = 'login.succeeded';
    case LoginFailed = 'login.failed';
    case LoginBlocked = 'login.blocked';
    case Logout = 'logout';
    case PasswordResetRequested = 'password_reset.requested';
    case PasswordResetCompleted = 'password_reset.completed';
    case PasswordResetFailed = 'password_reset.failed';
    case PasswordChanged = 'password.changed';

    public function label(): string
    {
        return match ($this) {
            self::LoginSucceeded => 'Signed in',
            self::LoginFailed => 'Failed sign-in attempt',
            self::LoginBlocked => 'Sign-in blocked by rate limit',
            self::Logout => 'Signed out',
            self::PasswordResetRequested => 'Password reset requested',
            self::PasswordResetCompleted => 'Password reset completed',
            self::PasswordResetFailed => 'Password reset failed',
            self::PasswordChanged => 'Password changed',
        };
    }

    public function isFailure(): bool
    {
        return in_array($this, [
            self::LoginFailed,
            self::LoginBlocked,
            self::PasswordResetFailed,
        ], true);
    }
}
