<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * An audit entry's properties, read as a collection like spatie's own cast. Made
 * safe as they're set, before anything serializes them: serializing a model would
 * decrypt its encrypted attributes (#84), and that happens the moment spatie's
 * withProperties() assigns them.
 *
 * Unreadable JSON reads as empty, as with spatie's cast, so one bad row can't break
 * the screens or the export.
 *
 * @implements CastsAttributes<Collection<array-key, mixed>|null, mixed>
 */
class AuditProperties implements CastsAttributes
{
    /** Laravel would otherwise keep the value as assigned, models and all, and run it through set() again on every save. */
    public bool $withoutObjectCaching = true;

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<array-key, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Collection
    {
        return $value === null ? null : new Collection(json_decode($value, true) ?? []);
    }

    /** @param  array<string, mixed>  $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : json_encode(app(LogAuditEntry::class)->storableProperties($value), JSON_THROW_ON_ERROR);
    }
}
