<?php

namespace Tests\Feature;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Runs composer.json's hooks:install script, which post-install-cmd and
 * post-update-cmd call, so `composer install` works with or without a git checkout.
 */
class ComposerHooksInstallTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/hooks-install-'.Str::random(8);
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_install_and_update_both_run_the_hooks_script(): void
    {
        $scripts = $this->composerScripts();

        $this->assertContains('@hooks:install', $scripts['post-install-cmd']);
        $this->assertContains('@hooks:install', $scripts['post-update-cmd']);
    }

    public function test_it_succeeds_outside_a_git_checkout(): void
    {
        $result = $this->runHooksInstall();

        $this->assertTrue($result->successful(), $result->errorOutput());
        $this->assertFileDoesNotExist($this->directory.'/.git');
    }

    public function test_it_points_git_at_the_committed_hooks_inside_a_checkout(): void
    {
        Process::path($this->directory)->run(['git', 'init', '--quiet'])->throw();

        $result = $this->runHooksInstall();

        $this->assertTrue($result->successful(), $result->errorOutput());
        $this->assertSame(
            '.githooks',
            trim(Process::path($this->directory)->run(['git', 'config', 'core.hooksPath'])->output()),
        );
    }

    /**
     * @return array<string, string|list<string>>
     */
    private function composerScripts(): array
    {
        return json_decode(File::get(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR)['scripts'];
    }

    /**
     * Runs the script's `@php -r` body the way Composer would, in the temp directory.
     */
    private function runHooksInstall(): ProcessResult
    {
        $code = Str::of($this->composerScripts()['hooks:install'])
            ->after('@php -r ')
            ->trim('"')
            ->replace('\\"', '"')
            ->toString();

        return Process::path($this->directory)->run([PHP_BINARY, '-r', $code]);
    }
}
