<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ExampleTest extends TestCase
{
    /**
     * The control plane holds credentials for every institution, so it has no
     * public landing page — the root redirects to the operator sign-in.
     */
    public function test_the_root_redirects_guests_to_sign_in(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_the_health_endpoint_is_public(): void
    {
        $this->get('/up')->assertOk();
    }
}
