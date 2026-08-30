<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinator;

/**
 * Same shape as creating, with every field optional. Omitting `items` leaves
 * the existing ones untouched; sending it replaces them wholesale.
 */
final class UpdateActivityTemplateRequest extends ActivityTemplateRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'project_type_id' => ['nullable', 'integer', 'exists:project_types,id'],
            'is_sequential' => ['boolean'],
            'is_active' => ['boolean'],
        ], $this->itemRules(sometimes: true));
    }
}
