<?php

declare(strict_types=1);

namespace App\Console\Concerns;

use App\Support\Setup\MarkerStripper;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * The file handling shared by the one-shot setup transforms (bp:remove-teams,
 * bp:make-login-only): strip fences, read and write files so a failed check
 * can put them back, lint, and boot artisan from the files on disk.
 */
trait TransformsSourceFiles
{
    /** Strip one file's fences, naming the file in any error. */
    private function strip(MarkerStripper $stripper, string $file, string $contents): string
    {
        try {
            return $stripper->strip($contents);
        } catch (RuntimeException $e) {
            throw new RuntimeException($this->relative($file).': '.$e->getMessage(), previous: $e);
        }
    }

    /** Why the file doesn't parse, or null when it does. */
    private function lint(string $file): ?string
    {
        $lint = Process::run([PHP_BINARY, '-l', $file]);

        return $lint->failed() ? trim($lint->output().$lint->errorOutput()) : null;
    }

    /**
     * Run an artisan command in a fresh process, so it boots from the files on disk.
     *
     * @param  array<string, string>  $environment
     */
    private function runArtisan(string $command, ?string &$output = null, array $environment = []): bool
    {
        $result = Process::path(base_path())->env($environment)->run([PHP_BINARY, 'artisan', $command, '--no-interaction']);
        $output = trim($result->errorOutput() ?: $result->output());

        return $result->successful();
    }

    /** @param  array<string, string>  $contents */
    private function write(array $contents): void
    {
        collect($contents)->each(function (string $content, string $file): void {
            File::ensureDirectoryExists(dirname($file));
            File::put($file, $content);
        });
    }

    /**
     * @param  list<string>  $files
     * @return array<string, string>
     */
    private function read(array $files): array
    {
        return collect($files)
            ->filter(File::exists(...))
            ->mapWithKeys(fn (string $file): array => [$file => File::get($file)])
            ->all();
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
