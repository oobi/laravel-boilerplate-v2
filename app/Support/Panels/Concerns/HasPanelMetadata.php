<?php

declare(strict_types=1);

namespace App\Support\Panels\Concerns;

use App\Support\Panels\PanelRegion;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Sensible defaults for panel metadata so a concrete panel only has to
 * override what actually differs from the default (main region, order 0,
 * always visible, key derived from the class name).
 */
trait HasPanelMetadata
{
    public function key(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    public function order(): int
    {
        return 0;
    }

    public function region(): PanelRegion
    {
        return PanelRegion::Main;
    }

    public function visible(Model $subject, ?Authenticatable $viewer): bool
    {
        return true;
    }
}
