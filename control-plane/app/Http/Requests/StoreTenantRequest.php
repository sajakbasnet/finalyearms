<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class StoreTenantRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:40', 'unique:tenants,slug'],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'An institution with that identifier already exists.',
            'admin_email.email' => 'Enter a valid email for the first Coordinator.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
        }

        $this->merge(['slug' => Str::lower(trim((string) $this->input('slug')))]);
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $slug = (string) $this->input('slug');

            // Same rule the pipeline enforces: the slug is interpolated into a
            // hostname, a database name and a container name.
            if ($slug !== '' && ! Tenant::isValidSlug($slug)) {
                $validator->errors()->add(
                    'slug',
                    'Use 2-40 lowercase letters, digits and hyphens, starting with a letter and ending with a letter or digit.',
                );
            }
        });
    }
}
