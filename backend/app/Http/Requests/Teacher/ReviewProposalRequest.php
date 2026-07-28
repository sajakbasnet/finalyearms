<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewProposalRequest extends FormRequest
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
            'action' => ['required', Rule::in(['approve', 'reject', 'request_revision'])],
            'comment' => ['nullable', 'string', 'max:5000'],
            'section' => ['nullable', 'string', 'max:100'],
        ];
    }
}
