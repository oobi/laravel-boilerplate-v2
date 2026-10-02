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

    /** @var list<string> */
    public array $sizes = ['xs', 'sm', 'md', 'lg', 'xl'];

    // <x-date-picker> demos, each showing the value it sets.
    public string $period = 'upcoming';

    public string $stay = '';

    public string $appointment = '';

    public string $dueDate = '';

    // <x-listbox> demo.
    public string $priority = '';

    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    public function render(): View
    {
        return view('theme-demo::livewire.component-gallery')->layout('layouts.admin');
    }
}
