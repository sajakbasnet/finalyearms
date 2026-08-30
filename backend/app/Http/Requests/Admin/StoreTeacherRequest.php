<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTeacherRequest extends FormRequest
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
            'employee_id' => ['required', 'string', 'max:50', 'unique:teachers,employee_id'],
            'designation' => ['nullable', 'string', 'max:255'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'max_projects' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
