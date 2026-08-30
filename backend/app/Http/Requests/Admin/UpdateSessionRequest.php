<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSessionRequest extends FormRequest
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
            'name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('academic_sessions', 'name')->ignore($this->route('session')),
            ],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $session = $this->route('session');

            if ($session === null) {
                return;
            }

            // Either bound may be absent on a partial update, so compare
            // against what the session already holds rather than requiring both.
            $start = $this->date('start_date') ?? $session->start_date;
            $end = $this->date('end_date') ?? $session->end_date;

            if ($start !== null && $end !== null && $end->lte($start)) {
                $validator->errors()->add('end_date', 'The end date must be after the start date.');

                return;
            }

            // Narrowing a session past dates already recorded in it would leave
            // a calendar that contradicts itself.
            $stranded = $session->dates()
                ->where(fn ($query) => $query->where('date', '<', $start)->orWhere('date', '>', $end))
                ->count();

            if ($stranded > 0) {
                $validator->errors()->add(
                    'start_date',
                    "{$stranded} key date(s) would fall outside these dates. Move or remove them first.",
                );
            }
        });
    }
}
