<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Teams;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * Core tests pass without the teams tier, so bp:remove-teams only has to
 * delete tests/Feature/Teams (GitHub #22). A test that needs the tier belongs
 * there; a core test asserts against Destination::home() or a stand-in
 * resolver rather than a team route.
 */
class CoreTestsStandAloneTest extends TestCase
{
    /** What only resolves while the tier is installed. */
    private const PATTERNS = [
        '/Concise\\\\Teams\b/',
        '/\bteam_(route|trans)\(/',
        '/\b(route|to_route|signedRoute|temporarySignedRoute|assertRedirectToRoute)\(\s*[\'"]teams?\./',
        // The tier's SystemPermission cases are stripped with it.
        '/SystemPermission::\w*TEAM/',
    ];

    /** Files that mention the tier as data, not a dependency. */
    private const ALLOWED = [
        'tests/Unit/Support/Teams/MarkerStripperTest.php',
        'tests/Unit/Support/Teams/CoreTestsStandAloneTest.php',
    ];

    public function test_no_core_test_depends_on_the_teams_tier(): void
    {
        $found = collect(File::allFiles(base_path('tests')))
            ->map(fn (SplFileInfo $file): string => str_replace(base_path().'/', '', $file->getPathname()))
            ->reject(fn (string $path): bool => str_starts_with($path, 'tests/Feature/Teams/') || in_array($path, self::ALLOWED, true))
            ->filter(fn (string $path): bool => collect(self::PATTERNS)->contains(fn (string $pattern): bool => preg_match($pattern, File::get(base_path($path))) === 1))
            ->values()
            ->all();

        $this->assertSame([], $found, 'These core tests need the teams tier; move the team cases to tests/Feature/Teams.');
    }
}
