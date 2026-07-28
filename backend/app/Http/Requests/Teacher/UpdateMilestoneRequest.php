<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Enums\MilestoneStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMilestoneRequest extends FormRequest
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
            'status' => ['required', Rule::enum(MilestoneStatus::class)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
