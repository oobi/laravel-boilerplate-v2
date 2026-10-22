<?php

declare(strict_types=1);

namespace App\Support\Setup;

use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Removes the fenced marker blocks that delimit an optional part of core files:
 * the teams tier's contributions (`teams`, see docs/teams-distribution.md) and
 * the public landing page and sign-up (`public`). A pure string transform so
 * the removal logic is testable without touching the filesystem; the commands
 * (bp:remove-teams, bp:make-login-only) wire it to real files.
 *
 * A block runs from a line matching `// {marker}:start` to `// {marker}:end`
 * (either may carry trailing prose). By default the whole block is deleted; if
 * the block contains a `// {marker}:canonical: <text>` line, the block is
 * instead REPLACED by <text> at the start line's indentation — for seams like
 * User.php where deleting outright would remove code core still needs. A start
 * with no end throws rather than swallowing the rest of the file.
 */
final class MarkerStripper
{
    public function __construct(private readonly string $marker = 'teams') {}

    /**
     * PHP files under $directories with at least one fence (a start marker at
     * the start of a line, so prose that names the marker doesn't count).
     *
     * @param  list<string>  $directories
     * @return array<string, string> Contents keyed by path.
     */
    public function fencedFiles(array $directories): array
    {
        $directories = collect($directories)->filter(fn (string $directory): bool => is_dir($directory))->values()->all();

        if ($directories === []) {
            return [];
        }

        $startRe = '/^\s*\/\/\s*'.preg_quote($this->marker, '/').':start\b/m';

        return collect(Finder::create()->files()->in($directories)->name('*.php'))
            ->mapWithKeys(fn (SplFileInfo $file): array => [$file->getPathname() => $file->getContents()])
            ->filter(fn (string $contents): bool => preg_match($startRe, $contents) === 1)
            ->all();
    }

    public function strip(string $contents): string
    {
        $quoted = preg_quote($this->marker, '/');
        $startRe = '/^(\s*)\/\/\s*'.$quoted.':start\b/';
        $endRe = '/^\s*\/\/\s*'.$quoted.':end\b/';
        $canonicalRe = '/^\s*\/\/\s*'.$quoted.':canonical:[ ]?(.*)$/';

        $lines = explode("\n", $contents);
        $out = [];
        $stripped = false;

        for ($i = 0, $n = count($lines); $i < $n; $i++) {
            if (! preg_match($startRe, $lines[$i], $start)) {
                $out[] = $lines[$i];

                continue;
            }

            $stripped = true;
            $indent = $start[1];
            $canonical = null;
            $startLine = $i + 1;

            // Consume through the matching end marker, noting any canonical line.
            for ($i++; $i < $n && ! preg_match($endRe, $lines[$i]); $i++) {
                if (preg_match($canonicalRe, $lines[$i], $c)) {
                    $canonical = $c[1];
                }
            }

            if ($i === $n) {
                throw new RuntimeException("Unterminated {$this->marker}:start at line {$startLine}.");
            }

            if ($canonical !== null) {
                $out[] = $indent.$canonical;
            }
        }

        if (! $stripped) {
            return $contents;
        }

        // Collapse the blank-line runs a removed block can leave behind.
        return (string) preg_replace('/\n{3,}/', "\n\n", implode("\n", $out));
    }
}
