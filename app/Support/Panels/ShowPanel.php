<?php

declare(strict_types=1);

namespace App\Support\Panels;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/**
 * A freeform card rendered on a resource's Show page. Implementations are
 * free to build their markup however suits them (a Blade view, a component,
 * a Filament infolist render) — the registry only cares about placement.
 */
interface ShowPanel
{
    public function key(): string;

    public function order(): int;

    public function region(): PanelRegion;

    public function visible(Model $subject, ?Authenticatable $viewer): bool;

    public function render(Model $subject): View|Htmlable;
}
