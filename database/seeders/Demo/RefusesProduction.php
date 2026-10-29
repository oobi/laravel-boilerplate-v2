<?php

namespace Database\Seeders\Demo;

use RuntimeException;

/**
 * Demo seeders create accounts with the known password "password", one of them
 * a super admin, so they refuse to run in production. `db:seed --force` skips
 * the confirmation prompt; this doesn't. Each demo seeder checks for itself
 * because each can be run on its own with `--class`. The teams tier's demo
 * TeamSeeder uses it too, so it stays while either tier has a demo seeder.
 */
trait RefusesProduction
{
    /**
     * @throws RuntimeException in production
     */
    protected function refuseProduction(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(static::class.' seeds demo accounts with a known password and will not run in production.');
        }
    }
}
