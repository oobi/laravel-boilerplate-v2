<?php

declare(strict_types=1);

namespace App\Support\AccountArea;

/**
 * The area layout an account page renders inside: a Blade layout view and the
 * data it needs (e.g. the team for the team shell). See {@see AccountAreaRegistry}.
 */
final readonly class AccountShell
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $layout,
        public array $data = [],
    ) {}
}
