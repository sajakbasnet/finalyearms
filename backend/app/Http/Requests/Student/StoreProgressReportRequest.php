<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProgressReportRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'percentage_completed' => ['required', 'integer', 'min:0', 'max:100'],
            'demo_link' => ['nullable', 'url', 'max:255'],
            'git_repository' => ['nullable', 'url', 'max:255'],
        ];
    }
}
