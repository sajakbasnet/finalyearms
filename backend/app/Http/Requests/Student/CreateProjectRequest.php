<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

final class CreateProjectRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'is_team' => ['required', 'boolean'],
            // Defaults to the project title when omitted.
            'team_name' => ['nullable', 'string', 'max:150'],
        ];
    }
}
