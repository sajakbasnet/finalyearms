<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // No `exists` rule: validating the address against the users table
        // would let an unauthenticated caller enumerate accounts.
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
