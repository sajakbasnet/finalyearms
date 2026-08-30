<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

final class Branding extends Model
{
    /** Single-row settings table; not pluralised. */
    protected $table = 'branding';

    protected $fillable = [
        'institution_name',
        'short_name',
        'tagline',
        'logo_path',
        'favicon_path',
        'color_primary',
        'color_accent',
        'color_ink',
        'color_paper',
        'font_display',
        'font_sans',
        'font_stylesheet_url',
    ];

    /** Mirrors the column default so an unsaved instance renders sensibly. */
    protected $attributes = [
        'institution_name' => 'FYP Portal',
    ];

    /**
     * The tenant's branding row, created on first read.
     *
     * Every tenant database has exactly one, so callers never have to deal
     * with a missing record.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }

    /**
     * Shape consumed by the SPA. Kept here rather than in the controller so the
     * public endpoint and any admin preview cannot drift apart.
     *
     * @return array<string, mixed>
     */
    public function toBrandingPayload(): array
    {
        return [
            'institution_name' => $this->institution_name,
            'short_name' => $this->short_name,
            'tagline' => $this->tagline,
            'logo_url' => $this->publicUrl($this->logo_path),
            'favicon_url' => $this->publicUrl($this->favicon_path),
            'colors' => [
                'primary' => $this->color_primary,
                'accent' => $this->color_accent,
                'ink' => $this->color_ink,
                'paper' => $this->color_paper,
            ],
            'font_display' => $this->font_display,
            'font_sans' => $this->font_sans,
            'font_stylesheet_url' => $this->font_stylesheet_url,
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }
}
