<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProposalReplyRequest extends FormRequest
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
            'reply' => ['required', 'string', 'max:5000'],
        ];
    }
}
