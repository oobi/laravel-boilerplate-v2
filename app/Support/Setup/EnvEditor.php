<?php

declare(strict_types=1);

namespace App\Support\Setup;

/**
 * Sets keys in a `.env` file's contents: a pure string transform so bp:setup's
 * config-writing is testable without a real file (GitHub #44).
 *
 * Every live line for a key is set, since Dotenv lets the last one win. With
 * no live line, the first commented one (`# TEAMS_CREATION=...`) is uncommented
 * in place; with neither, the key is appended. The file's line ending is kept
 * (CRLF if it has any), and a live line keeps its `export`. Booleans render as `true`/`false`; a value of safe characters stays
 * bare, anything else is double-quoted with `\`, `"` and `$` escaped, so it
 * reads back exactly as given and `${...}` is never interpolated.
 */
final class EnvEditor
{
    /**
     * @param  array<string, string|bool>  $pairs
     */
    public function apply(string $env, array $pairs): string
    {
        $eol = str_contains($env, "\r\n") ? "\r\n" : "\n";
        // Any ending splits a line, so a file mixing LF and CRLF can't glue two lines together.
        $lines = $env === '' ? [] : preg_split('/\r\n|\n|\r/', rtrim($env, "\r\n"));

        foreach ($pairs as $key => $value) {
            $lines = $this->set($lines, $key, $this->format($value));
        }

        return implode($eol, $lines).$eol;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function set(array $lines, string $key, string $value): array
    {
        $quoted = preg_quote($key, '/');
        $line = $key.'='.$value;
        $livePattern = '/^(\s*(?:export\s+)?)'.$quoted.'\s*=/';
        $live = collect($lines)->filter(fn (string $existing): bool => preg_match($livePattern, $existing) === 1);

        if ($live->isNotEmpty()) {
            // Keep each line's own indent and `export`, for anyone who also sources the file.
            return collect($lines)
                ->map(fn (string $existing, int $index): string => $live->has($index) && preg_match($livePattern, $existing, $match) === 1 ? $match[1].$line : $existing)
                ->all();
        }

        $commented = collect($lines)->search(fn (string $existing): bool => preg_match('/^\s*#\s*'.$quoted.'\s*=/', $existing) === 1);

        if ($commented !== false) {
            $lines[$commented] = $line;

            return $lines;
        }

        return [...$lines, $line];
    }

    private function format(string|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (preg_match('/^[A-Za-z0-9_\-.\/:@+]+$/', $value) === 1) {
            return $value;
        }

        return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$', "\r\n" => '\\n', "\n" => '\\n']).'"';
    }
}
