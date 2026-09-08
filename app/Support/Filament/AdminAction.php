<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Actions\Action;
use Filament\Support\Enums\Size;

/**
 * A Filament action pre-styled for the app's action lists (pairs with the
 * <x-action-list> Blade component): an outlined, small trigger button. Use it
 * in place of `Action::make()` for any button that sits in an "Actions" card or
 * a panel's action row, so every screen gets the same treatment without
 * repeating `->outlined()->size(...)`.
 *
 * Colour, icon, label and behaviour are set per action as usual — those express
 * intent and aren't part of the shared look. Modal defaults (sticky header/
 * footer, outlined cancel, footer alignment) still come from
 * AppServiceProvider's global Action::configureUsing().
 */
class AdminAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->outlined()->size(Size::Small);
    }

    /**
     * Render as a soft button (tinted fill, dark coloured text, soft ring) — the
     * third emphasis level alongside the default outlined and Filament's solid.
     * See `.fi-btn-soft` in filament-buttons.css.
     */
    public function soft(bool $condition = true): static
    {
        if ($condition) {
            $this->outlined(false);
            $this->extraAttributes(['class' => 'fi-btn-soft'], merge: true);
        }

        return $this;
    }
}
