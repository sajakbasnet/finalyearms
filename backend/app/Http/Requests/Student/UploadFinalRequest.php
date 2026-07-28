<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

final class UploadFinalRequest extends FormRequest
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
            'thesis' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'presentation' => ['nullable', 'file', 'mimes:pdf,ppt,pptx', 'max:20480'],
            'github_repository' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->hasFile('thesis') && ! $this->hasFile('presentation') && ! $this->filled('github_repository')) {
                $validator->errors()->add('thesis', 'Upload at least a thesis, presentation, or GitHub link.');
            }
        });
    }
}
