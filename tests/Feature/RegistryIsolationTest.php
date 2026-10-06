<?php

namespace Tests\Feature;

use App\Support\Panels\Registry\PanelRegistry;
use Tests\TestCase;

/**
 * The registries are static and every app boot re-runs the providers that
 * fill them. Tests\TestCase flushes them before each boot; without that they
 * grow test after test and late page renders slow to a crawl.
 */
class RegistryIsolationTest extends TestCase
{
    public function test_booting_the_app_again_does_not_duplicate_registrations(): void
    {
        $firstBoot = $this->registrations();

        $this->tearDown();
        $this->setUp();

        $this->assertSame($firstBoot, $this->registrations());
    }

    /**
     * @return array{panels: list<class-string>}
     */
    private function registrations(): array
    {
        return [
            'panels' => PanelRegistry::for('users.show')->classes,
        ];
    }
}
