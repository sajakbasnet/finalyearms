<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinator;

use App\Enums\ActivityItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validation shared by creating and updating an activity template.
 *
 * Abstract rather than one request extending the other: concrete requests in
 * this codebase are `final`, and the per-type `config` rules need to live in
 * exactly one place or the two would drift the first time an item type gains a
 * setting.
 */
abstract class ActivityTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Item rules, minus `config`, which depends on each item's own `type` and
     * is checked in withValidator().
     *
     * @return array<string, mixed>
     */
    protected function itemRules(bool $sometimes = false): array
    {
        $prefix = $sometimes ? ['sometimes'] : [];

        return [
            'items' => array_merge($prefix, ['array', 'max:100']),
            'items.*.type' => ['required', 'string', 'in:'.implode(',', ActivityItemType::values())],
            'items.*.title' => ['required', 'string', 'max:200'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            // Offsets from project start, not absolute dates, so a template
            // survives into the next academic session unchanged.
            'items.*.due_offset_days' => ['nullable', 'integer', 'min:0', 'max:1095'],
            'items.*.blocks_progression' => ['nullable', 'boolean'],
            'items.*.config' => ['nullable', 'array'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                $type = ActivityItemType::tryFrom((string) ($item['type'] ?? ''));

                if ($type === null) {
                    continue; // The `in:` rule already reported this.
                }

                $this->validateConfigFor($validator, $type, $index, (array) ($item['config'] ?? []));
            }
        });
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function validateConfigFor(
        Validator $validator,
        ActivityItemType $type,
        int|string $index,
        array $config,
    ): void {
        $rules = $type->configRules();

        // An unrecognised key is almost always a typo. Storing it silently
        // would leave a setting that looks applied but does nothing.
        foreach (array_keys($config) as $key) {
            if (! array_key_exists($key, $rules)) {
                $validator->errors()->add(
                    "items.{$index}.config.{$key}",
                    "A {$type->label()} does not accept the setting \"{$key}\".",
                );
            }
        }

        if ($rules === []) {
            return;
        }

        $scoped = validator($config, $rules, $this->configMessages());

        foreach ($scoped->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $validator->errors()->add("items.{$index}.config.{$field}", $message);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function configMessages(): array
    {
        return [
            'cadence.required' => 'A Recurring Log needs a cadence (weekly, fortnightly or monthly).',
            'cadence.in' => 'Cadence must be weekly, fortnightly or monthly.',
            'occurrences.required' => 'A Recurring Log needs a number of occurrences.',
            'occurrences.max' => 'A Recurring Log cannot repeat more than 60 times.',
            'approver_role.required' => 'An Approval Gate needs an approver role.',
            'approver_role.in' => 'Only a supervisor, coordinator or institution admin can approve a gate.',
        ];
    }
}
