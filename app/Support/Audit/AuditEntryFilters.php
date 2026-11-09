<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AuditEntry;
use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the audit screen can filter by, in one place, so the CSV export always
 * holds exactly what the screen shows.
 */
final class AuditEntryFilters
{
    /** A positive whole number short enough to fit a bigint column. */
    public const string USER_ID = '/^[1-9][0-9]{0,17}$/D';

    /**
     * @param  Builder<AuditEntry>  $query
     * @param  array{category?: ?string, event?: ?string, actor_type?: ?string, date?: ?string, search?: ?string, user?: int|string|null}  $filters
     * @return Builder<AuditEntry>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        self::equals($query, 'log_name', $filters['category'] ?? null);
        self::equals($query, 'event', $filters['event'] ?? null);
        self::equals($query, 'actor_type', $filters['actor_type'] ?? null);
        self::date($query, $filters['date'] ?? null);
        self::search($query, $filters['search'] ?? null);
        self::forUser($query, $filters['user'] ?? null);

        return $query;
    }

    /** @param  Builder<AuditEntry>  $query */
    public static function equals(Builder $query, string $column, ?string $value): void
    {
        if (filled($value)) {
            $query->where($column, $value);
        }
    }

    /** @param  Builder<AuditEntry>  $query */
    public static function date(Builder $query, ?string $value): void
    {
        DateRange::parse($value)?->apply($query, 'created_at', config('app.timezone'));
    }

    /** @param  Builder<AuditEntry>  $query */
    public static function search(Builder $query, ?string $term): void
    {
        if (filled($term)) {
            $query->whereAnyContains(['description', 'event', 'actor_name', 'ip_address'], $term);
        }
    }

    /**
     * What a person did, and what was done to them.
     *
     * @param  Builder<AuditEntry>  $query
     */
    public static function forUser(Builder $query, int|string|null $userId): void
    {
        $id = self::userId($userId);

        if ($id === null) {
            return;
        }

        $type = (new User)->getMorphClass();

        $query->where(fn (Builder $query): Builder => $query
            ->where(fn (Builder $query): Builder => $query->where('causer_type', $type)->where('causer_id', $id))
            ->orWhere(fn (Builder $query): Builder => $query->where('subject_type', $type)->where('subject_id', $id)));
    }

    /** The user id a URL or form holds, or null when it isn't one a database could store. */
    public static function userId(int|string|null $value): ?int
    {
        return preg_match(self::USER_ID, (string) $value) === 1 ? (int) $value : null;
    }
}
