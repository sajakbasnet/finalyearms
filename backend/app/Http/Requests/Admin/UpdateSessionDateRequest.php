<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSessionDateRequest extends FormRequest
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
        $session = $this->route('session');

        return [
            'label' => [
                'sometimes', 'required', 'string', 'max:120',
                Rule::unique('academic_session_dates', 'label')
                    ->where(fn ($query) => $query->where('academic_session_id', $session?->id))
                    ->ignore($this->route('date')),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'date' => ['sometimes', 'required', 'date'],
            'is_deadline' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.unique' => 'That session already has a key date with this label.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $session = $this->route('session');
            $date = $this->date('date');

            if ($session === null || $date === null) {
                return;
            }

            // A key date outside its own session is almost always a typo, and
            // silently accepting it produces a calendar nobody can trust.
            if ($date->lt($session->start_date) || $date->gt($session->end_date)) {
                $validator->errors()->add(
                    'date',
                    'The date must fall within the session ('
                        .$session->start_date->toDateString().' to '
                        .$session->end_date->toDateString().').',
                );
            }
        });
    }
}
