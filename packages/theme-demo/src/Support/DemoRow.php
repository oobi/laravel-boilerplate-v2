<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Support;

use Concise\ThemeDemo\Enums\DemoStatus;

/** A single fabricated row for the table demos — deliberately not an Eloquent model. */
final readonly class DemoRow
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public DemoStatus $status,
        public string $joinedAt,
        public string $detail,
    ) {}
}
