<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProposalCommentRequest extends FormRequest
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
            'comment' => ['required', 'string', 'max:5000'],
            'section' => ['nullable', 'string', 'max:100'],
        ];
    }
}
