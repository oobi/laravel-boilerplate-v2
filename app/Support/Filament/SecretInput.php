<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Closure;
use Filament\Forms\Components\Field;

/**
 * A field for a secret (an API key, a password for another service) that never
 * sends the stored value to the browser. It shows whether one is saved, with
 * Replace (or Add) to type a new one and Remove to remove it on save. Left alone,
 * it isn't in the form's state at all, so saving the rest of the form keeps the
 * stored secret. Changed, its state is the new value; saved empty or cleared, null.
 *
 * In the browser a touched field holds ['value' => '...'], not a string:
 * Filament reads a blank string as null, which here means "untouched".
 * Validation rules see the typed string. required() means a secret must be
 * saved: left alone it passes when one is stored; removed or empty, it fails.
 *
 *     SecretInput::make('api_key')->label(__('...'))->noun(__('...key'))->stored(fn (): bool => filled($settings->api_key))
 */
class SecretInput extends Field
{
    protected string $view = 'filament.forms.components.secret-input';

    protected bool|Closure $isStored = false;

    protected string|Closure|null $noun = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Whatever the form was filled with, the browser starts with nothing.
        $this->afterStateHydrated(fn (SecretInput $component): SecretInput => $component->state(null));

        // Rules check what was typed; a value that isn't a string (a tampered request) fails.
        $this->mutateStateForValidationUsing(fn (mixed $state): mixed => is_array($state) ? ($state['value'] ?? null) : $state);
        $this->rule('string');

        // Null is untouched (left out); ['value' => ''] is "make it empty".
        $this->dehydrated(fn (mixed $state): bool => is_array($state));
        $this->dehydrateStateUsing(fn (mixed $state): ?string => is_array($state) && is_string($state['value'] ?? null) && $state['value'] !== '' ? $state['value'] : null);
    }

    /** Whether a secret is stored now, so the field can say so (and offer Clear). */
    public function stored(bool|Closure $condition = true): static
    {
        $this->isStored = $condition;

        return $this;
    }

    public function isStored(): bool
    {
        return (bool) $this->evaluate($this->isStored);
    }

    /** A required secret left alone is fine while one is stored: it isn't being changed. */
    public function getRequiredValidationRule(): string|Closure
    {
        if ($this->isRequired() && $this->isStored() && $this->getRawState() === null) {
            return 'nullable';
        }

        return parent::getRequiredValidationRule();
    }

    /** What the secret is, in lower case, for the wording: "key" gives "Replace key", "No key saved". */
    public function noun(string|Closure|null $noun): static
    {
        $this->noun = $noun;

        return $this;
    }

    public function getNoun(): string
    {
        return $this->evaluate($this->noun) ?? __('admin.secret_noun');
    }
}
