<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinator;

final class StoreActivityTemplateRequest extends ActivityTemplateRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'project_type_id' => ['nullable', 'integer', 'exists:project_types,id'],
            'is_sequential' => ['boolean'],
        ], $this->itemRules());
    }
}
