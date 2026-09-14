<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\SystemPermission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Placeholder landing page after login — replace with real widgets/content.
 */
class Dashboard extends Component
{
    public function render(): View
    {
        // The roster is the users area's data: only a viewer who may open that
        // area sees names and addresses here. The count is a platform statistic.
        $canViewUsers = Gate::allows(SystemPermission::VIEW_USERS->value);

        return view('livewire.admin.dashboard', [
            'totalUsers' => User::count(),
            'canViewUsers' => $canViewUsers,
            'recentUsers' => $canViewUsers ? User::latest()->take(5)->get() : collect(),
        ]);
    }
}
