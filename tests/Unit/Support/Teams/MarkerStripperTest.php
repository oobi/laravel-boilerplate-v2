<?php

namespace Tests\Unit\Support\Teams;

use App\Support\Teams\MarkerStripper;
use Tests\TestCase;

/**
 * The pure content transform behind bp:remove-teams — verified here on strings
 * so the destructive command's core logic is covered without touching files.
 */
class MarkerStripperTest extends TestCase
{
    public function test_it_deletes_a_plain_fenced_block(): void
    {
        $in = <<<'PHP'
            use App\Foo;
            // teams:start
            use Concise\Teams\Bar;
            // teams:end
            use App\Baz;
            PHP;

        $out = (new MarkerStripper)->strip($in);

        $this->assertStringNotContainsString('Concise\Teams', $out);
        $this->assertStringNotContainsString('teams:start', $out);
        $this->assertStringContainsString('use App\Foo;', $out);
        $this->assertStringContainsString('use App\Baz;', $out);
    }

    public function test_it_replaces_a_block_with_its_canonical_line_preserving_indent(): void
    {
        $in = <<<'PHP'
            class User
            {
                // teams:start
                // teams:canonical: use A, B;
                use A, B, HasTeams {
                    HasTeams::teams insteadof B;
                }
                // teams:end
            }
            PHP;

        $out = (new MarkerStripper)->strip($in);

        $this->assertStringContainsString('    use A, B;', $out);
        $this->assertStringNotContainsString('HasTeams', $out);
        $this->assertStringNotContainsString('teams:canonical', $out);
    }

    public function test_it_collapses_blank_line_residue_a_removed_block_leaves(): void
    {
        $in = "a\n\n// teams:start\nx\n// teams:end\n\nb\n";

        $out = (new MarkerStripper)->strip($in);

        $this->assertStringNotContainsString("\n\n\n", $out);
        $this->assertStringContainsString('a', $out);
        $this->assertStringContainsString('b', $out);
    }

    public function test_it_leaves_content_without_markers_untouched(): void
    {
        $in = "line1\n\n\nline2\n"; // triple newline but no markers → unchanged

        $this->assertSame($in, (new MarkerStripper)->strip($in));
    }
}
