<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateStudentRequest extends FormRequest
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
        /** @var \App\Models\Student $student */
        $student = $this->route('student');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($student->user_id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'registration_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'registration_number')->ignore($student->id),
            ],
            'roll_number' => ['nullable', 'string', 'max:50'],
            'batch' => ['nullable', 'string', 'max:50'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
        ];
    }
}
