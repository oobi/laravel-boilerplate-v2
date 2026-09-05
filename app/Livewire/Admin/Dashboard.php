<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Placeholder landing page after login — replace with real widgets/content.
 */
class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.admin.dashboard', [
            'totalUsers' => User::count(),
            'recentUsers' => User::latest()->take(5)->get(),
        ]);
    }
}
