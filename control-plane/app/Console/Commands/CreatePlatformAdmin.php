<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Laravel\Prompts\password as promptPassword;

final class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:admin
                            {email : Operator email address}
                            {--name= : Display name}
                            {--generate : Generate a password instead of prompting}';

    protected $description = 'Create a platform operator who can sign in to the control plane.';

    public function handle(): int
    {
        $email = Str::lower((string) $this->argument('email'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("'{$email}' is not a valid email address.");

            return self::FAILURE;
        }

        if (PlatformAdmin::query()->where('email', $email)->exists()) {
            $this->error("An operator with {$email} already exists.");

            return self::FAILURE;
        }

        if ($this->option('generate')) {
            $password = Str::password(20);
            $this->warn("Generated password: {$password}");
            $this->line('It is not stored in plaintext and cannot be recovered.');
        } else {
            // Prompted rather than passed as an argument, so the password does
            // not end up in shell history or the process list.
            $password = promptPassword(
                label: 'Password',
                required: true,
                validate: fn (string $value): ?string => strlen($value) < 12
                    ? 'Use at least 12 characters.'
                    : null,
            );
        }

        PlatformAdmin::query()->create([
            'name' => (string) ($this->option('name') ?? 'Platform Operator'),
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->info("Operator {$email} created.");

        return self::SUCCESS;
    }
}
