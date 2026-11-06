<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AuditEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Spatie\Activitylog\Models\Activity;

/**
 * One line of the audit log. Append only: the model refuses to change or delete
 * one. The retention clean deletes old rows at query level, so it isn't affected.
 *
 * @property string $actor_type
 * @property string|null $actor_name
 * @property int|null $impersonator_id
 * @property string|null $impersonator_name
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class AuditEntry extends Activity
{
    /** @use HasFactory<AuditEntryFactory> */
    use HasFactory;

    /** Not an `updating` listener, because saveQuietly() and withoutEvents() would skip one. */
    protected function performUpdate(Builder $query): never
    {
        throw new LogicException('An audit entry cannot be changed.');
    }

    /**
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     * @param  string  $method
     */
    protected function incrementOrDecrement($column, $amount, $extra, $method): never
    {
        throw new LogicException('An audit entry cannot be changed.');
    }

    protected function performDeleteOnModel(): never
    {
        throw new LogicException('An audit entry cannot be deleted.');
    }

    /** @return BelongsTo<User, $this> */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id')->withTrashed();
    }
}
