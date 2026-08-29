<?php

declare(strict_types=1);

namespace App\Support\Panels;

use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * A contributor of fields to a resource's single Edit form. Unlike
 * ShowPanel, this must return Filament schema components since Edit pages
 * are one Filament form/statePath, not freeform markup.
 */
interface FormSection
{
    public function key(): string;

    public function order(): int;

    public function region(): PanelRegion;

    public function visible(Model $subject, ?Authenticatable $viewer): bool;

    /** @return array<int, Component> */
    public function components(Model $subject): array;
}
