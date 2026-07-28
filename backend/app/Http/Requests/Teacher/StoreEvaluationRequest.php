<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

final class StoreEvaluationRequest extends FormRequest
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
            'innovation' => ['required', 'integer', 'min:0', 'max:100'],
            'implementation' => ['required', 'integer', 'min:0', 'max:100'],
            'documentation' => ['required', 'integer', 'min:0', 'max:100'],
            'presentation' => ['required', 'integer', 'min:0', 'max:100'],
            'testing' => ['required', 'integer', 'min:0', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
