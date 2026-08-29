<?php

declare(strict_types=1);

namespace App\Support\Panels;

use Closure;

/**
 * Optional companion to ShowPanel for panels that trigger a mutation (e.g. a
 * "force disable 2FA" button). The host Livewire component resolves the
 * panel by key and invokes the named closure, so panels never need direct
 * knowledge of the host component's internals.
 */
interface HasPanelActions
{
    /** @return array<string, Closure> */
    public function actions(): array;
}
