<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * A date or range picked with <x-date-picker>: "Y-m-d" (one day) or
 * "Y-m-d/Y-m-d" (Cally's range value, in either order). Days are calendar
 * days in a given timezone; apply() turns them into UTC instants for a
 * timestamp column, since times are stored in UTC.
 */
final readonly class DateRange
{
    private function __construct(
        public string $from,
        /** Null for open-ended: every day from $from on. */
        public ?string $to,
    ) {}

    /** The picked days, or null when the value is anything else (a preset, empty, junk). */
    public static function parse(mixed $value): ?self
    {
        if (! is_string($value) || ! preg_match('/^(\d{4}-\d{2}-\d{2})(?:\/(\d{4}-\d{2}-\d{2}))?$/', $value, $days)) {
            return null;
        }

        $from = $days[1];
        $to = $days[2] ?? $from;

        foreach ([$from, $to] as $day) {
            if (CarbonImmutable::createFromFormat('!Y-m-d', $day)?->format('Y-m-d') !== $day) {
                return null;
            }
        }

        return $from <= $to ? new self($from, $to) : new self($to, $from);
    }

    /** From the first day, open-ended. */
    public static function from(CarbonImmutable $day): self
    {
        return new self($day->toDateString(), null);
    }

    public static function day(CarbonImmutable $day): self
    {
        return new self($day->toDateString(), $day->toDateString());
    }

    /**
     * Limit a timestamp column to these days in the timezone.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, string $column, string $timezone): Builder
    {
        $query->where($column, '>=', CarbonImmutable::parse($this->from, $timezone)->utc());

        if ($this->to !== null) {
            $query->where($column, '<', CarbonImmutable::parse($this->to, $timezone)->addDay()->utc());
        }

        return $query;
    }

    public function isSingleDay(): bool
    {
        return $this->from === $this->to;
    }
}
