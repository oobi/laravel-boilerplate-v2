<?php

namespace Tests\Unit\Support\Setup;

use App\Support\Setup\EnvEditor;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The pure `.env` transform behind bp:setup, verified on strings and checked
 * against Dotenv itself, so what bp:setup writes is what the app reads (#44).
 */
class EnvEditorTest extends TestCase
{
    public function test_it_updates_an_existing_key_in_place(): void
    {
        $out = (new EnvEditor)->apply("APP_ENV=local\nTEAMS_CREATION=self-service\n", [
            'TEAMS_CREATION' => 'admin-only',
        ]);

        $this->assertStringContainsString('TEAMS_CREATION=admin-only', $out);
        $this->assertStringNotContainsString('self-service', $out);
        $this->assertStringContainsString('APP_ENV=local', $out); // untouched
    }

    public function test_it_uncomments_and_sets_a_commented_key(): void
    {
        $out = (new EnvEditor)->apply("# TEAMS_MEMBER_INVITATIONS=true\n", [
            'TEAMS_MEMBER_INVITATIONS' => false,
        ]);

        $this->assertStringContainsString('TEAMS_MEMBER_INVITATIONS=false', $out);
        $this->assertStringNotContainsString('# TEAMS_MEMBER_INVITATIONS', $out);
    }

    public function test_it_appends_a_missing_key(): void
    {
        $out = (new EnvEditor)->apply("APP_ENV=local\n", ['LOGIN_FALLBACK' => 'reject']);

        $this->assertStringContainsString("APP_ENV=local\n", $out);
        $this->assertStringContainsString('LOGIN_FALLBACK=reject', $out);
    }

    public function test_it_quotes_values_containing_whitespace(): void
    {
        $out = (new EnvEditor)->apply('', ['TEAMS_MEMBER_LABEL_PLURAL' => 'Team Leaders']);

        $this->assertStringContainsString('TEAMS_MEMBER_LABEL_PLURAL="Team Leaders"', $out);
    }

    public function test_it_writes_a_value_with_regex_metachars_literally(): void
    {
        // `$1`/`\0` in the value must not be treated as preg backreferences.
        $out = (new EnvEditor)->apply("SOME_KEY=old\n", ['SOME_KEY' => 'a$1b\0c']);

        $this->assertSame('a$1b\0c', Dotenv::parse($out)['SOME_KEY']);
    }

    /** @return array<string, array{string}> */
    public static function awkwardValues(): array
    {
        return [
            'quotes and an apostrophe' => ['Bob\'s "Best" App'],
            'a hash' => ['Acme#1'],
            'a hash after a space' => ['Acme #1'],
            'a variable reference' => ['Pay ${APP_KEY} now'],
            'a dollar' => ['$5 Deals'],
            'backslashes' => ['C:\\path\\n'],
            'empty' => [''],
            'leading and trailing spaces' => ['  padded  '],
            'a plain word' => ['Acme'],
            'a newline' => ["two\nlines"],
        ];
    }

    #[DataProvider('awkwardValues')]
    public function test_a_value_reads_back_exactly_as_written(string $value): void
    {
        $out = (new EnvEditor)->apply("APP_ENV=local\n", ['APP_NAME' => $value]);

        $this->assertSame($value, Dotenv::parse($out)['APP_NAME']);
    }

    public function test_a_variable_reference_is_not_interpolated_on_a_real_load(): void
    {
        $dir = sys_get_temp_dir().'/env-editor-'.uniqid();
        File::ensureDirectoryExists($dir);
        File::put($dir.'/.env', (new EnvEditor)->apply("SECRET=hunter2\n", ['APP_NAME' => 'Pay ${SECRET} now']));

        try {
            $this->assertSame('Pay ${SECRET} now', Dotenv::createArrayBacked($dir)->load()['APP_NAME']);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_the_value_that_takes_effect_is_the_one_written(): void
    {
        // Kept the .env.example layout, then appended an override below it.
        $env = "# TEAMS_CREATION=self-service\nAPP_ENV=local\nTEAMS_CREATION=admin-only\n";

        $out = (new EnvEditor)->apply($env, ['TEAMS_CREATION' => 'self-service']);

        $this->assertSame('self-service', Dotenv::parse($out)['TEAMS_CREATION']);
        $this->assertStringStartsWith('# TEAMS_CREATION=self-service', $out, 'the commented example is left alone');
    }

    public function test_every_live_duplicate_is_set(): void
    {
        $out = (new EnvEditor)->apply("APP_NAME=First\nAPP_ENV=local\nAPP_NAME=Second\n", ['APP_NAME' => 'Third']);

        $this->assertSame("APP_NAME=Third\nAPP_ENV=local\nAPP_NAME=Third\n", $out);
    }

    public function test_a_crlf_file_keeps_its_line_endings(): void
    {
        $out = (new EnvEditor)->apply("APP_NAME=Old\r\n# TEAMS_CREATION=x\r\nAPP_ENV=local\r\n", [
            'APP_NAME' => 'New Name',
            'TEAMS_CREATION' => 'admin-only',
            'LOGIN_FALLBACK' => 'home',
        ]);

        $this->assertSame("APP_NAME=\"New Name\"\r\nTEAMS_CREATION=admin-only\r\nAPP_ENV=local\r\nLOGIN_FALLBACK=home\r\n", $out);
    }

    public function test_a_file_mixing_line_endings_keeps_every_other_line(): void
    {
        $out = (new EnvEditor)->apply("A=1\r\nAPP_KEY=base64:x\nB=2\r\nC=3\n", ['APP_KEY' => 'k', 'C' => '4']);

        $this->assertSame(['A' => '1', 'APP_KEY' => 'k', 'B' => '2', 'C' => '4'], Dotenv::parse($out));
        $this->assertSame("A=1\r\nAPP_KEY=k\r\nB=2\r\nC=4\r\n", $out);
    }

    public function test_an_exported_key_stays_exported(): void
    {
        $out = (new EnvEditor)->apply("export APP_NAME=Old\n", ['APP_NAME' => 'New']);

        $this->assertSame("export APP_NAME=New\n", $out);
    }
}
