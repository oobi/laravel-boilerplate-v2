<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

use Filament\Support\Contracts\HasLabel;

/**
 * Shared `options()` for string-backed enums implementing Filament's
 * HasLabel, e.g. for Filament SelectFilter/select field options.
 *
 * @method static list<static> cases()
 */
trait HasOptions
{
    /** @return array<string, string> value => label, for select/dropdown options. */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            /** @var \BackedEnum&HasLabel $case */
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
