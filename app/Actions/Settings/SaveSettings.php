<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Settings\EditSettings;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditCategory;
use App\Support\Audit\LogAuditEntry;
use App\Support\Filament\SecretInput;
use App\Support\Settings\SettingsSection;
use App\Support\Settings\SettingsSections;
use Filament\Forms\Components\Field;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelSettings\Settings;

/**
 * Save the Settings screen, section by section, and log what changed. Only a
 * section the person may see is written, only through the fields it defines,
 * and never a locked setting. So a caller other than the screen can't reach a
 * setting through a section that has no field for it, even when two sections
 * share a settings class. A field hidden or disabled by a condition is still
 * a field: restrict access with the section's visible() or lock(). A secret
 * (an encrypted setting, or one shown with a SecretInput) is logged without
 * its value.
 */
class SaveSettings
{
    /**
     * @param  array<string, array<string, mixed>>  $data  validated form state, keyed by section
     * @return list<string> the settings that changed, as "group.name"
     */
    public function handle(array $data, User $by): array
    {
        Gate::forUser($by)->authorize(SystemPermission::MANAGE_SETTINGS);

        return DB::transaction(function () use ($data, $by): array {
            $changes = SettingsSections::for($by)
                ->map(fn (SettingsSection $section): array => $this->save($section, $data[$section->key()] ?? []))
                ->collapse();

            if ($changes->isNotEmpty()) {
                Audit::recordChanges('settings.updated', $changes->all(), category: AuditCategory::SYSTEM, by: $by);
            }

            return $changes->keys()->all();
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array{label: string, before: mixed, after: mixed}> keyed "group.name"
     */
    private function save(SettingsSection $section, array $values): array
    {
        /** @var Settings $settings */
        $settings = app($section->settings());
        $current = $settings->toArray();
        $fields = $this->fields($section);

        $changed = collect($values)
            ->only(array_intersect(array_keys($fields), array_keys($current)))
            ->reject(fn (mixed $value, string $name): bool => $settings->isLocked($name) || $current[$name] === $value);

        if ($changed->isEmpty()) {
            return [];
        }

        $settings->fill($changed->all())->save();

        $isSecret = fn (string $name): bool => $fields[$name] instanceof SecretInput || in_array($name, $settings::encrypted(), true);

        return $changed
            ->mapWithKeys(fn (mixed $value, string $name): array => [$settings::group().'.'.$name => [
                'label' => (string) ($fields[$name]->getLabel() ?? $name),
                'before' => $this->logged($current[$name], $isSecret($name)),
                'after' => $this->logged($value, $isSecret($name)),
            ]])
            ->all();
    }

    /** A secret's value never reaches the log. A null stays null, so "set" and "cleared" still show. */
    private function logged(mixed $value, bool $isSecret): mixed
    {
        return $isSecret && $value !== null ? LogAuditEntry::REDACTED : $value;
    }

    /**
     * The fields the section defines, by name, as the screen builds them. Their
     * conditions aren't judged here: that needs the screen's whole state.
     *
     * @return array<string, Field>
     */
    private function fields(SettingsSection $section): array
    {
        $fields = Schema::make(app(EditSettings::class))
            ->components($section->components())
            ->getFlatFields(withHidden: true);

        return collect($fields)->keyBy(fn (Field $field): string => $field->getName())->all();
    }
}
