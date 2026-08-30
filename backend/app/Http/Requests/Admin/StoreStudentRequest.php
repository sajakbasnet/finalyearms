<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreStudentRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'registration_number' => ['required', 'string', 'max:50', 'unique:students,registration_number'],
            'roll_number' => ['nullable', 'string', 'max:50'],
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
        ];
    }
}
