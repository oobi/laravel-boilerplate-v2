<?php

declare(strict_types=1);

namespace App\Support\Panels\Contracts;

/**
 * Companion to {@see HasPanelActions} for panels whose actions are sensitive
 * enough to require a fresh password confirmation before they run. The host
 * Livewire component checks this before invoking the action and, for a guarded
 * one, routes it through an inline password prompt first.
 */
interface HasGuardedActions
{
    /**
     * Action keys (from {@see HasPanelActions::actions()}) that must not run
     * without a fresh password confirmation.
     *
     * @return array<int, string>
     */
    public function guardedActions(): array;
}
