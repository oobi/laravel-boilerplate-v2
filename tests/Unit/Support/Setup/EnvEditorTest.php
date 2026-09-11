<?php

namespace Tests\Unit\Support\Setup;

use App\Support\Setup\EnvEditor;
use Tests\TestCase;

/**
 * The pure `.env` transform behind bp:setup — verified on strings so the
 * command's config-writing is covered without a real file.
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

        $this->assertStringContainsString('SOME_KEY=a$1b\0c', $out);
    }
}
