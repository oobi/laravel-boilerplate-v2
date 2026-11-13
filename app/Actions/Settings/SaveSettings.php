<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Enums\SystemPermission;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditCategory;
use App\Support\Settings\SettingsSection;
use App\Support\Settings\SettingsSections;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelSettings\Settings;

/**
 * Save the Settings screen, section by section, and log what changed. Only a
 * section the person may see is written, only its settings class's own
 * properties, and never a locked one. Encrypted settings are named in the log
 * but their values are left out.
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
                $shown = $changes->reject(fn (array $change): bool => $change['encrypted']);

                Audit::record('settings.updated', properties: [
                    'changed' => $changes->keys()->all(),
                    'before' => $shown->map(fn (array $change): mixed => $change['before'])->all(),
                    'after' => $shown->map(fn (array $change): mixed => $change['after'])->all(),
                ], category: AuditCategory::SYSTEM, by: $by);
            }

            return $changes->keys()->all();
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array{before: mixed, after: mixed, encrypted: bool}> keyed "group.name"
     */
    private function save(SettingsSection $section, array $values): array
    {
        /** @var Settings $settings */
        $settings = app($section->settings());
        $current = $settings->toArray();

        $changed = collect($values)
            ->only(array_keys($current))
            ->reject(fn (mixed $value, string $name): bool => $settings->isLocked($name) || $current[$name] === $value);

        if ($changed->isEmpty()) {
            return [];
        }

        $settings->fill($changed->all())->save();

        return $changed
            ->mapWithKeys(fn (mixed $value, string $name): array => [$settings::group().'.'.$name => [
                'before' => $current[$name],
                'after' => $value,
                'encrypted' => in_array($name, $settings::encrypted(), true),
            ]])
            ->all();
    }
}
