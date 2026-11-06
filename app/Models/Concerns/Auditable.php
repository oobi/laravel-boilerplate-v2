<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Logs this model's creates, changes, deletes and restores to the audit log,
 * with before and after values. Sensitive attributes are redacted wherever they
 * come from (App\Support\Audit\LogAuditEntry), so `use Auditable;` is all a model
 * needs. A custom cast that decrypts a value can't be detected: list that
 * attribute in `$hidden`.
 *
 * A write and its entry commit together, so if the entry can't be written the
 * write fails too. A change is never committed without its record. A model that
 * defines its own save() or delete() replaces the versions here, so to keep that
 * promise it must alias them and call the alias, not parent:
 * `use Auditable { Auditable::save as auditedSave; }`. A subclass of an audited
 * model can call parent as usual.
 *
 * To leave out an attribute that only adds noise or bulk, or can't be stored as
 * text, declare `protected array $auditExcept = ['last_seen_at'];` on the model.
 *
 * Query-builder updates and deletes fire no model events, so they aren't seen:
 * record those with Audit::record().
 *
 * The overrides below match Eloquent's untyped signatures on purpose. A return
 * type here would be a fatal error for a subclass with its own untyped method,
 * and a TypeError for a Pivot, whose delete() returns an int.
 */
trait Auditable
{
    use LogsActivity;

    private bool $firingRestoredFromSave = false;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(property_exists($this, 'auditExcept') ? $this->auditExcept : [])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * SoftDeletes::restore() fires `restored` after its save, and the two traits
     * can't both define restore(). So `restored` fires here, inside the transaction,
     * and fireModelEvent() drops the one restore() fires afterwards.
     *
     * @param  array<string, mixed>  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            $isRestoring = $this->isRestoring();
            $saved = parent::save($options);

            if ($saved && $isRestoring) {
                $this->firingRestoredFromSave = true;

                try {
                    $this->fireModelEvent('restored', false);
                } finally {
                    $this->firingRestoredFromSave = false;
                }
            }

            return $saved;
        });
    }

    /** @return bool|int|null */
    public function delete()
    {
        return $this->getConnection()->transaction(fn () => parent::delete());
    }

    /**
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     * @param  string  $method
     * @return int|false
     */
    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        return $this->getConnection()->transaction(fn () => parent::incrementOrDecrement($column, $amount, $extra, $method));
    }

    /**
     * @param  string  $event
     * @param  bool  $halt
     * @return mixed
     */
    protected function fireModelEvent($event, $halt = true)
    {
        if ($event === 'restored' && ! $this->firingRestoredFromSave) {
            return true;
        }

        return parent::fireModelEvent($event, $halt);
    }
}
