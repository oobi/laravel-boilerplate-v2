<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Actions\Action;
use Filament\Support\Enums\Size;

/**
 * A Filament action pre-styled for the app's action lists (pairs with the
 * <x-action-list> Blade component): a soft, small trigger button. Use it in
 * place of `Action::make()` for any button that sits in an "Actions" card or a
 * panel's action row, so every screen gets the same treatment. Soft keeps the
 * intent colour while staying a step below the solid primary action in a page
 * or list header, so a stack of actions doesn't shout.
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

        static::styleForList($this);
    }

    /**
     * Give the action-list look (soft, small) to an action built elsewhere, such
     * as a shared factory whose action is solid in a list header but sits in an
     * "Actions" card here. See `.fi-btn-soft` in filament-buttons.css.
     */
    public static function styleForList(Action $action): Action
    {
        return $action
            ->size(Size::Small)
            ->extraAttributes(['class' => 'fi-btn-soft'], merge: true);
    }
}
