<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/** A subclass overriding save() and delete() the way Eloquent declares them, with no return type. */
class AuditedWidgetWithOwnWrites extends AuditedWidget
{
    public function save(array $options = [])
    {
        return parent::save($options);
    }

    public function delete()
    {
        return parent::delete();
    }
}
