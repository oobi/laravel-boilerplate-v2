<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Livewire\Component as Livewire;

/**
 * A "Generate password" link beside the label of a password field an admin
 * fills in for someone else (New user, Set password). It fills the field and
 * its confirmation with a strong random password from the server, then
 * reveals it so the admin can read it out. Once the field holds a generated
 * password the link reads "Regenerate" and a "Copy password" link appears
 * under the field, beside the minimum-length hint: worded links, not icons,
 * so nothing is a mystery. Apply it with `->tap(GeneratePasswordAction::on(...))`
 * on a `->revealable()` field, in place of its own helperText(). The hint
 * shares its row with the copy link, so on() writes it and adds it to the
 * input's aria-describedby ahead of Filament's own ids (the error message).
 *
 * The admin passes the password on, often by reading it out, so it uses
 * letters and digits without lookalikes (no 0/O, 1/l/I) in groups of five
 * joined by dashes: four groups carry about 115 bits. Every one has a digit,
 * a capital and a lower case letter, and the dash is a symbol, so it passes a
 * project's tighter Password::defaults() too, not only the minimum length.
 */
class GeneratePasswordAction extends Action
{
    private const string ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const int GROUP_LENGTH = 5;

    private const int MIN_GROUPS = 4;

    protected string $confirmationField = 'password_confirmation';

    public static function getDefaultName(): ?string
    {
        return 'generatePassword';
    }

    /** Add the generate and copy links to a revealable password field. */
    public static function on(TextInput $field, string $confirmationField = 'password_confirmation'): TextInput
    {
        $copied = Js::from(__('admin.password_copied'));
        $hintId = fn (TextInput $component): string => $component->getId().'-password-hint';

        return $field
            ->hintAction(static::make()->confirmationField($confirmationField))
            ->belowContent(fn (TextInput $component): Schema => Schema::between([
                Text::make(__('auth.password_min_hint', ['min' => config()->integer('auth.password_min_length')]))
                    ->extraAttributes(['id' => $hintId($component)]),
                Action::make('copyPassword')
                    ->label(__('admin.copy_password'))
                    ->icon(Heroicon::OutlinedClipboardDocument)
                    ->visible(static::isGenerated($component->getState()))
                    ->alpineClickHandler("window.navigator.clipboard.writeText(\$el.closest('.fi-fo-field').querySelector('input.fi-input').value); \$tooltip({$copied}, { theme: \$store.theme, timeout: 2000 })"),
            ]))
            ->extraInputAttributes(fn (TextInput $component): array => [
                'aria-describedby' => implode(' ', [$hintId($component), ...$component->getDescriptionIds()]),
            ], merge: true)
            ->extraAttributes(fn (TextInput $component): array => [
                'x-on:password-generated.window' => 'if ($event.detail.field === '.Js::from($component->getStatePath()).') { setPasswordRevealed(true) }',
            ], merge: true);
    }

    /** At least four groups, more if the configured minimum length needs them. */
    public static function generate(): string
    {
        // g groups are 6g - 1 characters long, dashes included.
        $groups = max(self::MIN_GROUPS, (int) ceil((config()->integer('auth.password_min_length') + 1) / (self::GROUP_LENGTH + 1)));
        $last = strlen(self::ALPHABET) - 1;

        do {
            $password = collect(range(1, $groups))
                ->map(fn (): string => collect(range(1, self::GROUP_LENGTH))->map(fn (): string => self::ALPHABET[random_int(0, $last)])->implode(''))
                ->implode('-');
        } while (! preg_match('/\d/', $password) || ! preg_match('/[A-Z]/', $password) || ! preg_match('/[a-z]/', $password));

        return $password;
    }

    /** Whether a value is one generate() made (a typed password won't be this shape). */
    public static function isGenerated(mixed $value): bool
    {
        $character = '['.preg_quote(self::ALPHABET, '/').']';

        return is_string($value)
            && preg_match("/^{$character}{".self::GROUP_LENGTH."}(-{$character}{".self::GROUP_LENGTH.'}){'.(self::MIN_GROUPS - 1).',}$/', $value) === 1;
    }

    public function confirmationField(string $name): static
    {
        $this->confirmationField = $name;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (TextInput $component): string => static::isGenerated($component->getState())
            ? __('admin.regenerate_password')
            : __('admin.generate_password'));
        $this->icon(Heroicon::OutlinedSparkles);

        $this->action(function (TextInput $component, Set $set, Livewire $livewire): void {
            $password = static::generate();

            $component->state($password);
            $set($this->confirmationField, $password);

            // A "must match" error from an earlier submit no longer applies.
            $statePath = $component->getStatePath();
            $livewire->resetValidation([$statePath, Str::beforeLast($statePath, '.').'.'.$this->confirmationField]);

            $livewire->dispatch('password-generated', field: $component->getStatePath());

            // Announced to screen readers, which otherwise only hear focus move to Hide password.
            Notification::make()->title(__('admin.password_generated'))->success()->send();
        });
    }
}
