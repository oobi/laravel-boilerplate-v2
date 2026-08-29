<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire\Tables;

use App\Enums\SystemPermission;
use Concise\ThemeDemo\Support\DemoRow;
use Concise\ThemeDemo\Support\DemoRows;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SimpleTable extends Component
{
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    /** @return Collection<int, DemoRow> */
    #[Computed]
    public function rows(): Collection
    {
        return DemoRows::take(8)
            ->when($this->search !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (DemoRow $row): bool => str_contains(mb_strtolower($row->name), mb_strtolower($this->search))
            ))
            ->values();
    }

    public function render(): View
    {
        return view('theme-demo::livewire.tables.simple')->layout('layouts.admin');
    }
}
