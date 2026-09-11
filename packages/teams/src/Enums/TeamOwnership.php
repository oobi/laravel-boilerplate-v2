<?php

declare(strict_types=1);

namespace Concise\Teams\Enums;

/**
 * What being a team's owner grants — `config('teams.ownership')`. In `sovereign`
 * (the SaaS default) the team is a self-governing entity and owners bypass team
 * permissions within their own team; in `managed` the team is a platform-controlled
 * silo and the "owner" is an operational manager whose authority comes only from
 * their assigned role (no bypass). Ownership itself is always structural
 * (teams.user_id); this only decides whether that status carries power. Read
 * through current() so the rule lives here, not as string comparisons at call sites.
 */
enum TeamOwnership: string
{
    case SOVEREIGN = 'sovereign';
    case MANAGED = 'managed';

    public static function current(): self
    {
        return self::tryFrom((string) config('teams.ownership', self::SOVEREIGN->value)) ?? self::SOVEREIGN;
    }

    /** Does owner status grant authority directly (bypass), or only via roles? */
    public function ownersBypass(): bool
    {
        return $this === self::SOVEREIGN;
    }
}
