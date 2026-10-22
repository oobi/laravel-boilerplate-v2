<?php

declare(strict_types=1);

namespace Tests\Feature\Teams;

use App\Console\Commands\RemoveTeamsCommand;
use App\Support\Teams\MarkerStripper;
use App\Support\Teams\TierReferences;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The tier's seams in core, checked against what bp:remove-teams relies on:
 * every fence strips to code that works without the tier (stripped into a
 * scratch copy, never in place), and TierReferences knows every helper. Lives
 * here because there is nothing to check once the tier is removed.
 */
class TierFencesTest extends TestCase
{
    public function test_every_real_fence_strips_to_code_that_works_without_the_tier(): void
    {
        $fenced = (new MarkerStripper)->fencedFiles(collect(RemoveTeamsCommand::ROOTS)->map(fn (string $root): string => base_path($root))->all());
        $this->assertNotEmpty($fenced);

        $scratch = sys_get_temp_dir().'/stripped-'.uniqid();
        File::ensureDirectoryExists($scratch);

        try {
            foreach ($fenced as $file => $contents) {
                $path = str_replace(base_path().'/', '', $file);
                $stripped = (new MarkerStripper)->strip($contents);
                File::put($scratch.'/'.str_replace('/', '_', $path), $stripped);

                $this->assertTrue(Process::run([PHP_BINARY, '-l', $scratch.'/'.str_replace('/', '_', $path)])->successful(), $path.' does not lint once stripped');
                $this->assertSame([], (new TierReferences)->in($stripped), $path.' still needs the tier');
            }

            // The routes file must also run: a variable defined only inside the fence would be undefined here.
            (static function (string $routes): void {
                require $routes;
            })($scratch.'/routes_web.php');
        } finally {
            File::deleteDirectory($scratch);
        }
    }

    public function test_it_knows_every_helper_the_tier_defines(): void
    {
        preg_match_all('/^\s*function (\w+)\(/m', file_get_contents(base_path('packages/teams/src/helpers.php')), $matches);

        $this->assertEqualsCanonicalizing($matches[1], TierReferences::HELPERS);
    }
}
