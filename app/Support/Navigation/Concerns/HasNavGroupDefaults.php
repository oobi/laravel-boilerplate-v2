<?php

declare(strict_types=1);

namespace App\Support\Navigation\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;

/** Sensible defaults so a concrete NavGroup only overrides what differs. */
trait HasNavGroupDefaults
{
    public function order(): int
    {
        return 0;
    }

    public function visible(?Authenticatable $viewer): bool
    {
        return true;
    }
}
