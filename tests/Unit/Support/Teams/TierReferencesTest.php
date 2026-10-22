<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Teams;

use App\Support\Teams\TierReferences;
use Tests\TestCase;

/**
 * What bp:remove-teams counts as code that still needs the teams tier.
 */
class TierReferencesTest extends TestCase
{
    public function test_it_finds_tier_names_and_helper_calls(): void
    {
        $php = <<<'PHP'
            <?php
            use Concise\Teams\Models\Team;
            $url = team_route('team.dashboard', $team);
            $label = \Concise\Teams\Support\TeamLabels::plural();
            use Concise\Teams\{Models\Domain, Support\TeamContext};
            $disk = team_disk(current_team());
            PHP;

        $this->assertSame([
            'line 2: Concise\Teams\Models\Team',
            'line 3: team_route',
            'line 4: \Concise\Teams\Support\TeamLabels',
            'line 5: Concise\Teams',
            'line 6: team_disk',
            'line 6: current_team',
        ], (new TierReferences)->in($php));
    }

    public function test_comments_and_strings_dont_count(): void
    {
        $php = <<<'PHP'
            <?php
            // Concise\Teams\Models\Team used to live here; team_route() too.
            /** See Concise\Teams\Support\TeamCoverage. */
            $note = 'team_trans(';
            PHP;

        $this->assertSame([], (new TierReferences)->in($php));
    }
}
