<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire\Tables;

use App\Enums\SystemPermission;
use Concise\ThemeDemo\Support\DemoRows;
use Concise\ThemeDemo\Support\WideDemoColumns;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/** Same ~20-column shape as the Filament "wide" variant, plain daisyUI markup — for comparing horizontal-scroll/responsive behaviour between the two. */
class WideTable extends Component
{
    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    /** @return list<string> */
    public function columnKeys(): array
    {
        return WideDemoColumns::keys();
    }

    public function columnLabel(string $key): string
    {
        return WideDemoColumns::label($key);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function rows(): Collection
    {
        return DemoRows::take(10)->map(fn ($row): array => [
            'name' => $row->name,
            'email' => $row->email,
            'status' => $row->status,
            'joined_at' => $row->joinedAt,
            ...WideDemoColumns::valuesFor($row),
        ])->values();
    }

    public function render(): View
    {
        return view('theme-demo::livewire.tables.wide')->layout('layouts.admin');
    }
}
