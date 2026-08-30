<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\ControlPlaneAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SessionController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    public function __construct(
        private readonly ControlPlaneAudit $audit,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $email = $request->string('email')->toString();
        $key = 'cp-login:'.Str::lower($email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            $this->audit->record('login.blocked', detail: ['email' => $email]);

            throw ValidationException::withMessages([
                'email' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $credentials = [
            'email' => $email,
            'password' => $request->string('password')->toString(),
            // A deactivated operator cannot sign in, and the check happens
            // inside the credential query rather than after it.
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            $this->audit->record('login.failed', detail: ['email' => $email]);

            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);

        // Prevents session fixation: the pre-login session id is discarded.
        $request->session()->regenerate();

        Auth::user()?->forceFill(['last_login_at' => now()])->save();

        $this->audit->record('login.succeeded', detail: ['email' => $email]);

        return redirect()->intended(route('tenants.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->audit->record('logout');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
