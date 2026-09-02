<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire\TabContent;

use App\Enums\SystemPermission;
use Concise\ThemeDemo\Support\DemoRow;
use Concise\ThemeDemo\Support\DemoRows;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TabContent extends Component
{
    public string $variant = 'table';

    public function mount(string $variant = 'table'): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);

        $this->variant = $variant;
    }

    /** @return Collection<int, DemoRow> */
    #[Computed]
    public function rows(): Collection
    {
        return DemoRows::take(8);
    }

    public function render(): View
    {
        return view('theme-demo::livewire.tab-content.index')->layout('layouts.admin');
    }
}
