<?php

declare(strict_types=1);

namespace App\Support\Setup;

/**
 * Sets keys in a `.env` file's contents — a pure string transform so bp:setup's
 * config-writing is testable without a real file. An existing key is updated in
 * place (even if commented out, e.g. `# TEAMS_CREATION=...`); a missing key is
 * appended. Booleans render as `true`/`false`; values containing whitespace are
 * double-quoted.
 */
final class EnvEditor
{
    /**
     * @param  array<string, string|bool>  $pairs
     */
    public function apply(string $env, array $pairs): string
    {
        foreach ($pairs as $key => $value) {
            $line = $key.'='.$this->format($value);
            $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $env) === 1) {
                // Callback (not a replacement string) so `$`/`\` in the value are
                // never mistaken for backreferences — cf. key:generate, which only
                // gets away with a plain replacement because base64 keys can't
                // contain them.
                $env = (string) preg_replace_callback($pattern, fn (): string => $line, $env, 1);

                continue;
            }

            $env = rtrim($env, "\n")."\n".$line."\n";
        }

        return $env;
    }

    private function format(string|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return preg_match('/\s/', $value) === 1 ? '"'.$value.'"' : $value;
    }
}
