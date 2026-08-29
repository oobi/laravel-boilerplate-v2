<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire;

use App\Enums\SystemPermission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ComponentGallery extends Component
{
    /** @var list<string> */
    public array $colors = ['primary', 'secondary', 'accent', 'neutral', 'info', 'success', 'warning', 'error'];

    /** @var list<string> */
    public array $buttonVariants = ['solid', 'soft', 'outline', 'dash', 'ghost'];

    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    public function render(): View
    {
        return view('theme-demo::livewire.component-gallery')->layout('layouts.admin');
    }
}
