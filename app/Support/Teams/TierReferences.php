<?php

declare(strict_types=1);

namespace App\Support\Teams;

use PhpToken;

/**
 * Finds code that still needs the teams tier: a `Concise\Teams\…` name or a
 * call to one of the tier's helpers. Comments don't count, so a docblock that
 * mentions the tier is fine. bp:remove-teams runs it over stripped core files
 * before removing the package.
 */
final class TierReferences
{
    /** Every helper in packages/teams/src/helpers.php. */
    public const HELPERS = ['team_trans', 'team_trans_choice', 'team_route', 'current_team', 'team_disk', 'team_cache'];

    /**
     * @return list<string> Each reference as "line N: text".
     */
    public function in(string $php): array
    {
        $found = [];
        $tokens = PhpToken::tokenize($php);

        foreach ($tokens as $index => $token) {
            if ($token->is([T_COMMENT, T_DOC_COMMENT])) {
                continue;
            }

            $name = ltrim($token->text, '\\');
            // A group import (`use Concise\Teams\{A, B};`) tokenises as the bare namespace.
            $isTierName = $token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
                && ($name === 'Concise\\Teams' || str_starts_with($name, 'Concise\\Teams\\'));
            $isHelperCall = $token->is(T_STRING) && in_array($token->text, self::HELPERS, true) && $this->nextMeaningful($tokens, $index)?->text === '(';

            if ($isTierName || $isHelperCall) {
                $found[] = "line {$token->line}: {$token->text}";
            }
        }

        return $found;
    }

    /** @param  list<PhpToken>  $tokens */
    private function nextMeaningful(array $tokens, int $index): ?PhpToken
    {
        for ($i = $index + 1, $n = count($tokens); $i < $n; $i++) {
            if (! $tokens[$i]->isIgnorable()) {
                return $tokens[$i];
            }
        }

        return null;
    }
}
