<?php

declare(strict_types=1);

/**
 * Runs the test suite and reports failures as a compact summary.
 *
 * PHPUnit has no summary-only mode: every failure prints its full assertion
 * diff, and a single `assertSee` against a rendered page can dump tens of
 * thousands of characters of HTML into the terminal. With more than one or two
 * failures that buries the information you actually need — which test, where.
 *
 * This wrapper streams the run's progress through untouched, suppresses the
 * detail blurt, and prints one entry per failure from the JUnit log instead.
 * Drill into any single failure the normal way:
 *
 *   composer test:summary -- --filter=test_name
 *   php artisan test --filter=test_name
 *
 * Any arguments are passed through to `artisan test`.
 */
const ANSI_RED = "\033[31m";
const ANSI_DIM = "\033[2m";
const ANSI_BOLD = "\033[1m";
const ANSI_RESET = "\033[0m";

/** Cap on how much of an assertion message to show before it stops being a summary. */
const MESSAGE_WIDTH = 100;

$root = dirname(__DIR__);
$junit = tempnam(sys_get_temp_dir(), 'junit') ?: exit(1);

// Mirrors the `test` script: a stale config cache makes the suite fail in ways
// that have nothing to do with the code under test.
passthru(sprintf('%s %s config:clear --ansi --quiet', escapeshellarg(PHP_BINARY), escapeshellarg($root.'/artisan')));

$passthrough = array_slice($argv, 1);

$command = array_merge(
    [PHP_BINARY, $root.'/artisan', 'test', '--parallel', '--compact', '--log-junit', $junit],
    $passthrough,
);

$exitCode = streamSuppressingDetails($command, $root);

if (is_file($junit) && filesize($junit) > 0) {
    printSummary($junit, $root);
}

@unlink($junit);

exit($exitCode);

/**
 * Run the suite, echoing output until PHPUnit starts listing failure details.
 *
 * The progress dots and header are genuinely useful, so they stream as normal.
 * Everything from "There was/were N failures:" onward is the blurt we replace.
 *
 * @param  list<string>  $command
 */
function streamSuppressingDetails(array $command, string $root): int
{
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    $process = proc_open($command, $descriptors, $pipes, $root);

    if (! is_resource($process)) {
        fwrite(STDERR, "Could not start the test runner.\n");

        return 1;
    }

    fclose($pipes[2]);
    stream_set_blocking($pipes[1], true);

    $suppressing = false;

    while (($line = fgets($pipes[1])) !== false) {
        // PHPUnit's detail sections are introduced by "There was 1 failure:",
        // "There were 3 errors:", and so on. Once one starts, nothing after it
        // is worth streaming — the JUnit log has the same information.
        if (! $suppressing && preg_match('/^There (was|were) \d+ /', $line) === 1) {
            $suppressing = true;
        }

        if (! $suppressing) {
            echo $line;
        }
    }

    fclose($pipes[1]);

    return proc_close($process);
}

/** Print one entry per failing test, plus the totals PHPUnit would have shown. */
function printSummary(string $junit, string $root): void
{
    $xml = @simplexml_load_file($junit);

    if ($xml === false) {
        return;
    }

    $failures = collectFailures($xml, $root);

    // A clean run needs nothing from us: PHPUnit's own "OK (n tests)" line has
    // already streamed through untouched.
    if ($failures === []) {
        return;
    }

    $totals = totals($junit);

    $width = max(array_map(static fn (array $f): int => strlen($f['location']), $failures));

    echo "\n";

    foreach ($failures as $failure) {
        printf(
            "  %s  %s  %s%s%s\n",
            ANSI_RED.str_pad($failure['type'], 7).ANSI_RESET,
            str_pad($failure['location'], $width),
            ANSI_BOLD,
            $failure['test'],
            ANSI_RESET,
        );

        if ($failure['message'] !== '') {
            printf("  %s  %s%s%s\n", str_repeat(' ', 7 + $width), ANSI_DIM, $failure['message'], ANSI_RESET);
        }
    }

    printf("\n  %s%s%s  %s\n\n", ANSI_RED, $totals, ANSI_RESET, ANSI_DIM.'re-run one with --filter='.ANSI_RESET);
}

/**
 * Pull every failing/erroring test case out of the JUnit log.
 *
 * @return list<array{type: string, location: string, test: string, message: string}>
 */
function collectFailures(SimpleXMLElement $xml, string $root): array
{
    $failures = [];

    foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $case) {
        foreach (['failure' => 'FAILED', 'error' => 'ERROR'] as $node => $label) {
            if (! isset($case->{$node})) {
                continue;
            }

            $body = (string) $case->{$node};

            $failures[] = [
                'type' => $label,
                'location' => locate($body, $root, (string) $case['file'], (int) $case['line']),
                'test' => shortTestName((string) $case['class'], (string) $case['name']),
                'message' => headlineMessage($body),
            ];
        }
    }

    return $failures;
}

/**
 * Find the most useful file:line for a failure.
 *
 * The stack trace runs outermost-last, so the final frame inside the project
 * (ignoring vendor) is the assertion that actually failed. Fall back to the
 * test case's own declared position when the trace gives us nothing.
 */
function locate(string $body, string $root, string $file, int $line): string
{
    preg_match_all('/^(\/.*?\.php):(\d+)$/m', $body, $matches, PREG_SET_ORDER);

    foreach (array_reverse($matches) as $match) {
        if (! str_contains($match[1], '/vendor/')) {
            return relative($match[1], $root).':'.$match[2];
        }
    }

    return relative($file, $root).':'.$line;
}

function relative(string $path, string $root): string
{
    return str_starts_with($path, $root.'/') ? substr($path, strlen($root) + 1) : $path;
}

/** `Tests\Feature\Admin\FooTest` + `test_it_works` -> `Admin\FooTest › it works`. */
function shortTestName(string $class, string $name): string
{
    $class = preg_replace('/^Tests\\\\(Feature|Unit)\\\\/', '', $class) ?? $class;
    $name = preg_replace('/^test_/', '', $name) ?? $name;

    return $class.' › '.str_replace('_', ' ', $name);
}

/**
 * Reduce a failure body to a single readable line.
 *
 * The first line is the fully-qualified test name, which we already show. The
 * line after it is either a custom assertion message — the most useful thing
 * available — or the start of PHPUnit's own diff, which may be an entire
 * rendered HTML document and gets truncated hard.
 */
function headlineMessage(string $body): string
{
    $lines = array_values(array_filter(
        preg_split('/\R/', $body) ?: [],
        static fn (string $line): bool => trim($line) !== '',
    ));

    $message = trim($lines[1] ?? '');

    if ($message === '') {
        return '';
    }

    if (mb_strlen($message) > MESSAGE_WIDTH) {
        return mb_substr($message, 0, MESSAGE_WIDTH).' …';
    }

    return $message;
}

/** Rebuild PHPUnit's own counts line from the JUnit root element. */
function totals(string $junit): string
{
    $xml = @simplexml_load_file($junit);

    if ($xml === false) {
        return '';
    }

    $suite = $xml->testsuite[0] ?? null;

    if ($suite === null) {
        return '';
    }

    $parts = [sprintf('%d tests', (int) $suite['tests'])];

    foreach (['failures' => 'failed', 'errors' => 'errors', 'skipped' => 'skipped'] as $attribute => $label) {
        if ((int) $suite[$attribute] > 0) {
            $parts[] = sprintf('%d %s', (int) $suite[$attribute], $label);
        }
    }

    // No timing here: PHPUnit's own "Time:" line has already streamed above us,
    // and the JUnit totals are the sum of each parallel worker's time, not wall clock.
    return implode(', ', $parts);
}
