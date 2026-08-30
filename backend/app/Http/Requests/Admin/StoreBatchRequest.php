<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBatchRequest extends FormRequest
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
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => [
                'required', 'string', 'max:100',
                // Unique within the department: "2079 Intake" in Computer
                // Science is a different cohort from the same name in Software
                // Engineering.
                Rule::unique('batches', 'name')->where(
                    fn ($query) => $query->where('department_id', $this->integer('department_id')),
                ),
            ],
            'intake_year' => ['required', 'integer', 'min:1980', 'max:2100'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'That department already has a batch with this name.',
        ];
    }
}
