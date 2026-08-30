<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinator;

use Illuminate\Foundation\Http\FormRequest;

final class CloneActivityTemplateRequest extends FormRequest
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
            // A clone starts a separate version line, so it needs its own name
            // to be distinguishable from the template it came from.
            'name' => ['required', 'string', 'max:150'],
        ];
    }
}
