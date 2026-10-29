<?php

declare(strict_types=1);

namespace App\Support\Setup;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Removes the fenced marker blocks that delimit an optional part of core files:
 * the teams tier's contributions (`teams`, see docs/boilerplate/teams-distribution.md) and
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
 *
 * A marker may sit in any line comment the file allows: `//` in PHP, `#` in
 * `.env`, `<!-- ... -->` in XML such as phpunit.xml (delete-only there: a
 * canonical line would keep the closing `-->`). Start and end are matched by
 * marker, not by comment style, so a file should use one style throughout.
 */
final class MarkerStripper
{
    /** The comment openers a marker may follow. */
    private const LEADER = '(?:\/\/|#|<!--)';

    public function __construct(private readonly string $marker = 'teams') {}

    /**
     * PHP files under $directories with at least one fence.
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

        return collect(Finder::create()->files()->in($directories)->name('*.php'))
            ->mapWithKeys(fn (SplFileInfo $file): array => [$file->getPathname() => $file->getContents()])
            ->filter($this->isFenced(...))
            ->all();
    }

    /**
     * Which of $files exist and have at least one fence, for files outside a
     * scanned directory (`.env`, `phpunit.xml`).
     *
     * @param  list<string>  $files
     * @return array<string, string> Contents keyed by path.
     */
    public function fencedAmong(array $files): array
    {
        return collect($files)
            ->filter(File::isFile(...))
            ->mapWithKeys(fn (string $file): array => [$file => File::get($file)])
            ->filter($this->isFenced(...))
            ->all();
    }

    /** Whether a start marker begins a line, so prose that names the marker doesn't count. */
    private function isFenced(string $contents): bool
    {
        return preg_match('/^\s*'.self::LEADER.'\s*'.preg_quote($this->marker, '/').':start\b/m', $contents) === 1;
    }

    public function strip(string $contents): string
    {
        $quoted = preg_quote($this->marker, '/');
        $startRe = '/^(\s*)'.self::LEADER.'\s*'.$quoted.':start\b/';
        $endRe = '/^\s*'.self::LEADER.'\s*'.$quoted.':end\b/';
        $canonicalRe = '/^\s*'.self::LEADER.'\s*'.$quoted.':canonical:[ ]?(.*)$/';

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
        return (string) preg_replace('/(\r?\n){3,}/', '$1$1', implode("\n", $out));
    }
}
