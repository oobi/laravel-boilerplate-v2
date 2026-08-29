<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire\Tables;

use App\Enums\SystemPermission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class EmptyTable extends Component
{
    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    public function render(): View
    {
        return view('theme-demo::livewire.tables.empty')->layout('layouts.admin');
    }
}
