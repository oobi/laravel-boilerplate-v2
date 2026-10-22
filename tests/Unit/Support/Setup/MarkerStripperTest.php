<?php

namespace Tests\Unit\Support\Setup;

use App\Support\Setup\MarkerStripper;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * The pure content transform behind bp:remove-teams, verified on strings. The
 * repo's real fences are in Tests\Feature\Teams\TierFencesTest.
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
        $this->assertStringNotContainsString("\r\n\r\n\r\n", (new MarkerStripper)->strip(str_replace("\n", "\r\n", $in)));
        $this->assertStringContainsString('a', $out);
        $this->assertStringContainsString('b', $out);
    }

    public function test_it_leaves_content_without_markers_untouched(): void
    {
        $in = "line1\n\n\nline2\n"; // triple newline but no markers → unchanged

        $this->assertSame($in, (new MarkerStripper)->strip($in));
    }

    public function test_an_unterminated_fence_throws_instead_of_truncating(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unterminated teams:start at line 2');

        (new MarkerStripper)->strip("a\n// teams:start\nb\nc\n");
    }

    public function test_it_strips_env_and_xml_comment_fences(): void
    {
        $env = "APP_NAME=App\n\n# teams:start (stripped with the tier)\nTEAMS_CREATION=admin-only\n# teams:end\n\nVITE_APP_NAME=App\n";
        $xml = "<php>\n    <env name=\"A\" value=\"1\"/>\n    <!-- teams:start -->\n    <env name=\"TEAMS_CREATION\" value=\"self-service\"/>\n    <!-- teams:end -->\n</php>\n";

        $this->assertSame("APP_NAME=App\n\nVITE_APP_NAME=App\n", (new MarkerStripper)->strip($env));
        $this->assertSame("<php>\n    <env name=\"A\" value=\"1\"/>\n</php>\n", (new MarkerStripper)->strip($xml));
    }

    public function test_fenced_among_finds_only_existing_fenced_files(): void
    {
        $dir = sys_get_temp_dir().'/fenced-among-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/.env', "# teams:start\nTEAMS_CREATION=admin-only\n# teams:end\n");
        file_put_contents($dir.'/phpunit.xml', "<!-- mentions teams:start mid-line, so not a fence -->\n");

        try {
            $found = (new MarkerStripper)->fencedAmong([$dir.'/.env', $dir.'/phpunit.xml', $dir.'/.env.example']);

            $this->assertSame([$dir.'/.env'], array_keys($found));
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
