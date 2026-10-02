<?php

namespace Tests\Unit\Support;

use App\Support\DateRange;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    #[TestWith(['2026-10-08', '2026-10-08', '2026-10-08'])]
    #[TestWith(['2026-10-08/2026-10-14', '2026-10-08', '2026-10-14'])]
    #[TestWith(['2026-10-14/2026-10-08', '2026-10-08', '2026-10-14'])]
    public function test_a_picked_day_or_range_is_read_in_order(string $value, string $from, string $to): void
    {
        $range = DateRange::parse($value);

        $this->assertSame([$from, $to], [$range?->from, $range?->to]);
    }

    #[TestWith(['upcoming'])]
    #[TestWith([''])]
    #[TestWith(['2026-02-30'])]
    #[TestWith(['2026-10-08/'])]
    #[TestWith(['8/10/2026'])]
    public function test_anything_else_is_not_a_range(string $value): void
    {
        $this->assertNull(DateRange::parse($value));
    }
}
