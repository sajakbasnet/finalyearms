<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class BrandingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_branding_is_readable_without_authentication(): void
    {
        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'institution_name',
                    'short_name',
                    'tagline',
                    'logo_url',
                    'favicon_url',
                    'colors' => ['primary', 'accent', 'ink', 'paper'],
                    'font_display',
                    'font_sans',
                    'font_stylesheet_url',
                ],
            ]);
    }

    public function test_branding_row_is_created_on_first_read(): void
    {
        Branding::query()->delete();

        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJsonPath('data.institution_name', 'FYP Portal');

        $this->assertSame(1, Branding::query()->count());
    }

    public function test_admin_can_update_branding(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());

        $this->postJson('/api/admin/branding', [
            'institution_name' => 'Tribhuvan University',
            'short_name' => 'TU',
            'tagline' => 'Final year project portal',
            'color_primary' => '#8c1d40',
            'color_accent' => '#ffc627',
        ])
            ->assertOk()
            ->assertJsonPath('data.institution_name', 'Tribhuvan University')
            ->assertJsonPath('data.colors.primary', '#8c1d40');

        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJsonPath('data.short_name', 'TU');
    }

    public function test_branding_update_rejects_non_hex_colours(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());

        $this->postJson('/api/admin/branding', [
            'institution_name' => 'Tribhuvan University',
            'color_primary' => 'red',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('color_primary');
    }

    public function test_branding_update_rejects_untrusted_font_host(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());

        $this->postJson('/api/admin/branding', [
            'institution_name' => 'Tribhuvan University',
            'font_stylesheet_url' => 'https://evil.example.com/fonts.css',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('font_stylesheet_url');
    }

    public function test_branding_update_accepts_allowed_font_host(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());

        $this->postJson('/api/admin/branding', [
            'institution_name' => 'Tribhuvan University',
            'font_stylesheet_url' => 'https://fonts.googleapis.com/css2?family=Inter',
        ])->assertOk();
    }

    public function test_branding_update_rejects_svg_logo(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());
        Storage::fake('public');

        $this->postJson('/api/admin/branding', [
            'institution_name' => 'Tribhuvan University',
            'logo' => UploadedFile::fake()->create('logo.svg', 8, 'image/svg+xml'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('logo');
    }

    public function test_admin_can_upload_a_logo(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());
        Storage::fake('public');

        $response = $this->postJson('/api/admin/branding', [
            'institution_name' => 'Tribhuvan University',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertOk();

        $this->assertNotNull($response->json('data.logo_url'));

        $path = Branding::current()->logo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_a_logo_removes_the_previous_file(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());
        Storage::fake('public');

        $this->postJson('/api/admin/branding', [
            'institution_name' => 'Tribhuvan University',
            'logo' => UploadedFile::fake()->image('first.png'),
        ])->assertOk();

        $firstPath = Branding::current()->logo_path;

        $this->postJson('/api/admin/branding', [
            'institution_name' => 'Tribhuvan University',
            'logo' => UploadedFile::fake()->image('second.png'),
        ])->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists(Branding::current()->logo_path);
    }

    public function test_non_admins_cannot_update_branding(): void
    {
        foreach (['teacher@fyp.local', 'student@fyp.local'] as $email) {
            Sanctum::actingAs(User::query()->where('email', $email)->firstOrFail());

            $this->postJson('/api/admin/branding', [
                'institution_name' => 'Hijacked University',
            ])->assertForbidden();
        }
    }

    public function test_guests_cannot_update_branding(): void
    {
        $this->postJson('/api/admin/branding', [
            'institution_name' => 'Hijacked University',
        ])->assertUnauthorized();
    }
}
