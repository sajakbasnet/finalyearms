<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBatchRequest extends FormRequest
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
        $batch = $this->route('batch');
        $departmentId = $this->integer('department_id') ?: $batch?->department_id;

        return [
            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('batches', 'name')
                    ->where(fn ($query) => $query->where('department_id', $departmentId))
                    ->ignore($batch),
            ],
            'intake_year' => ['sometimes', 'required', 'integer', 'min:1980', 'max:2100'],
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
