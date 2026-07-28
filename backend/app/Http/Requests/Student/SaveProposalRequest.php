<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

final class SaveProposalRequest extends FormRequest
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
            'abstract' => ['nullable', 'string'],
            'background' => ['nullable', 'string'],
            'problem_statement' => ['nullable', 'string'],
            'objectives' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'methodology' => ['nullable', 'string'],
            'literature_review' => ['nullable', 'string'],
            'timeline' => ['nullable', 'string'],
            'expected_outcome' => ['nullable', 'string'],
            'technologies' => ['nullable', 'string'],
            'references' => ['nullable', 'string'],
            'domain' => ['nullable', 'string', 'max:255'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
