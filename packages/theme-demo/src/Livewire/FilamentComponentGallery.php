<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire;

use App\Enums\SystemPermission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class FilamentComponentGallery extends Component
{
    /** Maps daisyUI's 8 semantic color names to Filament's own color keys (gray/danger differ). */
    public array $filamentColors = [
        'primary' => 'primary',
        'secondary' => 'secondary',
        'accent' => 'accent',
        'neutral' => 'gray',
        'info' => 'info',
        'success' => 'success',
        'warning' => 'warning',
        'error' => 'danger',
    ];

    /** @var list<string> */
    public array $sizes = ['xs', 'sm', 'md', 'lg', 'xl'];

    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    public function render(): View
    {
        return view('theme-demo::livewire.filament-component-gallery')->layout('layouts.admin');
    }
}
