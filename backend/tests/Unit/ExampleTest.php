<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class ExampleTest extends TestCase
{
    public function test_application_key_is_configured(): void
    {
        $this->assertNotEmpty(config('app.key'));
    }
}
