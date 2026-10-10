<?php

declare(strict_types=1);

namespace App\Support\Theme;

use Illuminate\Support\HtmlString;

/**
 * The visitor's light/dark choice (the `theme` cookie: light, dark or auto),
 * as every layout's <html> carries it. The server bakes in light and dark;
 * auto is resolved before first paint by <x-theme-init-script>, which knows
 * the OS preference the server can't. The header menu's theme buttons write
 * the cookie.
 */
final class ThemeMode
{
    public const string LIGHT_THEME = 'boilerplate';

    public const string DARK_THEME = 'boilerplate-dark';

    /** light | dark | auto, from the cookie (anything else reads as auto). */
    public static function current(): string
    {
        $mode = request()->cookie('theme', 'auto');

        return in_array($mode, ['light', 'dark', 'auto'], true) ? $mode : 'auto';
    }

    /** The <html> element's data-theme and data-theme-mode attributes. */
    public static function htmlAttributes(): HtmlString
    {
        $mode = self::current();

        return new HtmlString(sprintf(
            'data-theme="%s" data-theme-mode="%s"',
            $mode === 'dark' ? self::DARK_THEME : self::LIGHT_THEME,
            $mode,
        ));
    }
}
