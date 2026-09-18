<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateBrandingRequest extends FormRequest
{
    /** Hosts permitted to serve a tenant's font stylesheet. */
    public const ALLOWED_FONT_HOSTS = ['fonts.googleapis.com'];

    private const HEX_COLOR = 'regex:/^#[0-9a-fA-F]{6}$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('institution_name') || trim((string) $this->institution_name) === '') {
            $this->merge([
                'institution_name' => 'FYP Portal',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'institution_name' => ['nullable', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:255'],

            'color_primary' => ['nullable', 'string', self::HEX_COLOR],
            'color_accent' => ['nullable', 'string', self::HEX_COLOR],
            'color_ink' => ['nullable', 'string', self::HEX_COLOR],
            'color_paper' => ['nullable', 'string', self::HEX_COLOR],

            /*
             * SVG is deliberately excluded. Uploads are served from the
             * tenant's own origin, so an SVG carrying a <script> would run as
             * first-party code against a signed-in admin.
             */
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico', 'max:256'],

            'font_display' => ['nullable', 'string', 'max:120'],
            'font_sans' => ['nullable', 'string', 'max:120'],
            // Host is pinned in withValidator(): an arbitrary stylesheet URL is
            // a first-party injection point.
            'font_stylesheet_url' => ['nullable', 'url', 'starts_with:https://', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color_primary.regex' => 'Colours must be a 6-digit hex value such as #0f5c63.',
            'color_accent.regex' => 'Colours must be a 6-digit hex value such as #0f5c63.',
            'color_ink.regex' => 'Colours must be a 6-digit hex value such as #0f5c63.',
            'color_paper.regex' => 'Colours must be a 6-digit hex value such as #0f5c63.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $url = $this->input('font_stylesheet_url');

            if (! is_string($url) || $url === '') {
                return;
            }

            $host = parse_url($url, PHP_URL_HOST);

            if (! in_array($host, self::ALLOWED_FONT_HOSTS, true)) {
                $validator->errors()->add(
                    'font_stylesheet_url',
                    'Font stylesheets must be served from: '.implode(', ', self::ALLOWED_FONT_HOSTS).'.',
                );
            }
        });
    }
}
