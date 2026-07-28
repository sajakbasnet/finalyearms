<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_ok(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_api_login_route_exists(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable();
    }
}
