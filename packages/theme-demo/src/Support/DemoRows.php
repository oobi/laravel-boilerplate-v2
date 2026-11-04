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
        return self::take(count(self::NAMES));
    }

    /**
     * The first $count rows. Past the last name the names repeat, each with its own id.
     *
     * @return Collection<int, DemoRow>
     */
    public static function take(int $count): Collection
    {
        return Collection::times($count, function (int $number): DemoRow {
            $index = $number - 1;
            $name = self::NAMES[$index % count(self::NAMES)];
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

    /** Fixed fake "trashed" rows for the trash-toggle demo — just for the look, not a real soft-delete. */
    public static function trashed(): Collection
    {
        return collect([
            ['name' => 'Deleted Placeholder One', 'days' => 40],
            ['name' => 'Deleted Placeholder Two', 'days' => 55],
            ['name' => 'Deleted Placeholder Three', 'days' => 70],
        ])->values()->map(function (array $row, int $index): DemoRow {
            $slug = str_replace(' ', '.', mb_strtolower($row['name']));

            return new DemoRow(
                id: 900 + $index + 1,
                name: $row['name'],
                email: "{$slug}@example.com",
                status: DemoStatus::ARCHIVED,
                joinedAt: now()->subDays($row['days'])->format('Y-m-d'),
                detail: 'Trashed demo row — restore/force-delete are cosmetic only here.',
            );
        });
    }
}
