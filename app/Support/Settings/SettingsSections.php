<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The sections of the admin Settings screen. Core's are added in
 * App\Support\Panels\AdminPanels::define(); an app or add-on adds its own from
 * its provider's boot(), never by editing the screen.
 */
class SettingsSections
{
    /** @var list<class-string<SettingsSection>> */
    private static array $classes = [];

    /** @param  class-string<SettingsSection>  ...$classes */
    public static function add(string ...$classes): void
    {
        foreach ($classes as $class) {
            if (! is_subclass_of($class, SettingsSection::class)) {
                throw new InvalidArgumentException("[{$class}] is not a settings section.");
            }

            if (! in_array($class, self::$classes, true)) {
                self::$classes[] = $class;
            }
        }
    }

    /** @return Collection<int, SettingsSection> the viewer's sections, in order */
    public static function for(Authenticatable $viewer): Collection
    {
        return collect(self::$classes)
            ->map(fn (string $class): SettingsSection => app($class))
            ->filter(fn (SettingsSection $section): bool => $section->visible($viewer))
            ->sortBy(fn (SettingsSection $section): int => $section->order())
            ->values();
    }

    /** Drop all sections, for tests. */
    public static function flush(): void
    {
        self::$classes = [];
    }
}
