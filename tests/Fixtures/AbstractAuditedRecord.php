<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A model that was made abstract after the log named it, so it can't be loaded any more. */
abstract class AbstractAuditedRecord extends Model {}
