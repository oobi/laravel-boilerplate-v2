<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Support;

use Concise\ThemeDemo\Enums\DemoStatus;
use Illuminate\Support\Collection;

/** Fixed, deterministic fabricated data for the table demos — no DB involved. */
class DemoRows
{
    private const NAMES = [
        'Ava Thompson', 'Liam Carter', 'Sophie Nguyen', 'Noah Patel', 'Isla Rodriguez',
        'Ethan Kowalski', 'Mia Johansson', 'Lucas Silva', 'Chloe Dubois', 'Mason Yilmaz',
        'Grace O\'Brien', 'Oliver Fischer', 'Zara Haddad', 'Elijah Novak', 'Amara Okafor',
        'Benjamin Kim', 'Freya Andersen', 'Daniel Moreau', 'Nadia Petrova', 'Samuel Osei',
        'Ruby Tanaka', 'Gabriel Costa', 'Layla Hassan', 'Henry Sorensen', 'Priya Sharma',
    ];

    private const STATUSES = [
        DemoStatus::ACTIVE, DemoStatus::ACTIVE, DemoStatus::ACTIVE, DemoStatus::PENDING, DemoStatus::ARCHIVED,
    ];

    /** @return Collection<int, DemoRow> */
    public static function all(): Collection
    {
        return collect(self::NAMES)->values()->map(function (string $name, int $index): DemoRow {
            $slug = str_replace(' ', '.', mb_strtolower(str_replace("'", '', $name)));

            return new DemoRow(
                id: $index + 1,
                name: $name,
                email: "{$slug}@example.com",
                status: self::STATUSES[$index % count(self::STATUSES)],
                joinedAt: now()->subDays(($index + 1) * 11)->format('Y-m-d'),
                detail: "Row #{$index} — extra detail revealed when this row is expanded.",
            );
        });
    }

    /** @return Collection<int, DemoRow> */
    public static function take(int $count): Collection
    {
        return self::all()->take($count)->values();
    }
}
