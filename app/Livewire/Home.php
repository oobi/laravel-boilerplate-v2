<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Public landing page — reachable by guests and authenticated users alike.
 * Authenticated users get a welcome message and, if they hold a system role,
 * a link through to the admin dashboard (which the public layout itself
 * cannot link to unconditionally, since /admin is gated behind that role).
 */
class Home extends Component
{
    public function render(): View
    {
        return view('livewire.home')->layout('layouts.public');
    }
}
