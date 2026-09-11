<?php

declare(strict_types=1);

namespace App\Support\Teams;

/**
 * Removes the fenced marker blocks that delimit an add-on's contributions to
 * core files (see docs/teams-distribution.md). A pure string transform so the
 * removal logic is testable without touching the filesystem; the command
 * (bp:remove-teams) wires it to real files.
 *
 * A block runs from a line matching `// {marker}:start` to `// {marker}:end`
 * (either may carry trailing prose). By default the whole block is deleted; if
 * the block contains a `// {marker}:canonical: <text>` line, the block is
 * instead REPLACED by <text> at the start line's indentation — for seams like
 * User.php where deleting outright would remove code core still needs.
 */
final class MarkerStripper
{
    public function __construct(private readonly string $marker = 'teams') {}

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

            // Consume through the matching end marker, noting any canonical line.
            for ($i++; $i < $n && ! preg_match($endRe, $lines[$i]); $i++) {
                if (preg_match($canonicalRe, $lines[$i], $c)) {
                    $canonical = $c[1];
                }
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
