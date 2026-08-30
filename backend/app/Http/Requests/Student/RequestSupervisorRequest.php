<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

final class RequestSupervisorRequest extends FormRequest
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
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            // A minimum length because "please supervise us" tells a supervisor
            // nothing they can decide on.
            'rationale' => ['required', 'string', 'min:40', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rationale.min' => 'Explain why this supervisor suits your project — at least a couple of sentences.',
        ];
    }
}
