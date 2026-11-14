<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\AuditProperties;
use Database\Factories\AuditEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use LogicException;
use ReflectionClass;
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

    /**
     * Set by Audit::recordChanges() while the entry is written: the caller
     * vetted its changes, so LogAuditEntry redacts none of them by name. Never
     * stored.
     */
    public bool $changesVetted = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...parent::casts(), 'properties' => AuditProperties::class];
    }

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

    /**
     * Who did it, a soft-deleted person included, as spatie's subject() is with
     * include_soft_deleted_subjects: their entries keep their link and email.
     *
     * @return MorphTo<Model, $this>
     */
    public function causer(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScope(SoftDeletingScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id')->withTrashed();
    }

    /**
     * An entry outlives the code it describes: after bp:remove-teams its subject's
     * class is gone, or the name now belongs to something that isn't a model, or
     * to an abstract one.
     * That's a related record that's gone, not an error.
     *
     * @param  string  $target
     * @param  string  $name
     * @param  string  $type
     * @param  string  $id
     * @param  string|null  $ownerKey
     * @return MorphTo<Model, $this>
     */
    protected function morphInstanceTo($target, $name, $type, $id, $ownerKey): MorphTo
    {
        if (static::isLoadableType($target)) {
            return parent::morphInstanceTo($target, $name, $type, $id, $ownerKey);
        }

        return $this->newMorphTo($this->newQuery()->whereRaw('1 = 0'), $this, $id, $this->getKeyName(), $type, $name);
    }

    /**
     * The same when eager loading: an entry whose class is gone is left out of the
     * load, so its relation stays empty.
     *
     * @param  Builder<Model>  $query
     * @param  string  $foreignKey
     * @param  string|null  $ownerKey
     * @param  string  $type
     * @param  string  $relation
     * @return MorphTo<Model, $this>
     */
    protected function newMorphTo(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation): MorphTo
    {
        return new class($query, $parent, $foreignKey, $ownerKey, $type, $relation) extends MorphTo
        {
            protected function buildDictionary(EloquentCollection $models): void
            {
                parent::buildDictionary($models->filter(fn (Model $model): bool => ! $model->{$this->morphType}
                    || AuditEntry::isLoadableType($model->{$this->morphType})));
            }
        };
    }

    /** Whether a stored morph type still names a model Eloquent can load. */
    public static function isLoadableType(string $type): bool
    {
        $class = static::getActualClassNameForMorph($type);

        return is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract();
    }
}
