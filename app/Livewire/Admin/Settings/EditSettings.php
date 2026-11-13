<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Settings;

use App\Actions\Settings\SaveSettings;
use App\Enums\SystemPermission;
use App\Support\Filament\SecretInput;
use App\Support\Settings\SettingsSection;
use App\Support\Settings\SettingsSections;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use LogicException;

/**
 * The admin Settings screen: one form made of the registered sections
 * (SettingsSections), each filled from and saved to its own settings class.
 */
class EditSettings extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, array<string, mixed>> */
    public ?array $data = [];

    public function mount(): void
    {
        Gate::authorize(SystemPermission::MANAGE_SETTINGS);

        $this->fillForm();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(
                $this->sections()
                    ->map(fn (SettingsSection $section): Group => Group::make($section->components())->statePath($section->key()))
                    ->all(),
            )
            ->statePath('data');
    }

    public function save(SaveSettings $saveSettings): void
    {
        Gate::authorize(SystemPermission::MANAGE_SETTINGS);

        $saveSettings->handle($this->form->getState(), Auth::user());

        // From what's now stored: a secret field goes back to saying whether one is saved, and drops what was typed.
        $this->fillForm();

        Notification::make()->title(__('settings.saved'))->success()->send();
    }

    public function render(): View
    {
        return view('livewire.admin.settings.edit-settings');
    }

    private function fillForm(): void
    {
        $fields = collect($this->form->getFlatFields(withHidden: true))
            ->mapWithKeys(fn (Field $field): array => [$field->getStatePath() => $field]);

        $this->form->fill(
            $this->sections()
                ->mapWithKeys(fn (SettingsSection $section): array => [$section->key() => $this->shownValues($section, $fields)])
                ->all(),
        );
    }

    /**
     * The section's stored values for the fields it shows, never an encrypted
     * one: whatever the form is filled with ends up in the page.
     *
     * @param  Collection<string, Field>  $fields  every field, by state path
     * @return array<string, mixed>
     */
    private function shownValues(SettingsSection $section, Collection $fields): array
    {
        $settings = app($section->settings());
        $shown = fn (string $name): ?Field => $fields->get("data.{$section->key()}.{$name}");

        // Any other field would start empty and save that over the secret.
        collect($settings::encrypted())
            ->filter(fn (string $name): bool => $shown($name) !== null && ! $shown($name) instanceof SecretInput)
            ->each(fn (string $name) => throw new LogicException("Show the encrypted setting [{$settings::group()}.{$name}] with a SecretInput."));

        // Belt and braces: the check above means only a SecretInput shows one, and it starts empty anyway.
        return collect($settings->toArray())
            ->except($settings::encrypted())
            ->filter(fn (mixed $value, string $name): bool => $shown($name) !== null)
            ->all();
    }

    /** @return Collection<int, SettingsSection> */
    private function sections(): Collection
    {
        return SettingsSections::for(Auth::user());
    }
}
