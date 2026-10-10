<?php

namespace Tests\Unit\Theme;

use PHPUnit\Framework\TestCase;

/**
 * The themes' text tokens clear WCAG AA (4.5:1), read straight from
 * resources/css/theme/tokens.css so a later tweak can't quietly undo it
 * (GitHub #31, #50).
 */
class ThemeContrastTest extends TestCase
{
    public function test_muted_text_clears_aa_on_the_page_and_the_raised_surface(): void
    {
        $this->assertSame(1, preg_match('/@utility text-muted \{\s*color: color-mix\(in oklab, var\(--color-base-content\) (\d+)%, transparent\);/', $this->css(), $muted), 'the text-muted utility is missing or reshaped');
        $text = $this->token('base-content');

        foreach (['base-100', 'base-200'] as $surface) {
            $background = $this->token($surface);

            $this->assertContrast($this->over($text, $background, $muted[1] / 100), $background, "text-muted on {$surface}");
        }
    }

    public function test_white_text_on_the_status_colours_clears_aa(): void
    {
        foreach (['success', 'info', 'error', 'primary'] as $name) {
            $this->assertContrast([1.0, 1.0, 1.0], $this->token($name), $name);
        }
    }

    /**
     * Brand blue, grey and red as text (--color-primary-text, --color-neutral-text,
     * --color-error-text: the soft
     * mix toward black in light, white in dark) on both themes' page surfaces (#50).
     */
    public function test_brand_blue_grey_and_red_as_text_clear_aa_in_both_themes(): void
    {
        foreach (['boilerplate', 'boilerplate-dark'] as $theme) {
            $this->assertSame(1, preg_match('/--soft-fg-mix:\s*(black|white);/', $this->themeBlock($theme), $mix), "{$theme}: --soft-fg-mix");
            $this->assertSame(1, preg_match('/--soft-fg-strength:\s*(\d+)%;/', $this->themeBlock($theme), $strength), "{$theme}: --soft-fg-strength");

            foreach (['primary', 'neutral', 'error'] as $name) {
                $this->assertStringContainsString(
                    "--color-{$name}-text: color-mix(in oklab, var(--color-{$name}) var(--soft-fg-strength), var(--soft-fg-mix));",
                    $this->themeBlock($theme),
                    "{$theme}: --color-{$name}-text is missing or reshaped",
                );

                $text = $this->mixInOklab($this->token($name, $theme), $mix[1] === 'white' ? [1.0, 1.0, 1.0] : [0.0, 0.0, 0.0], $strength[1] / 100);

                foreach (['base-100', 'base-200'] as $surface) {
                    $this->assertContrast($text, $this->token($surface, $theme), "{$name} as text on {$surface} ({$theme})");
                }
            }
        }
    }

    /**
     * @param  array{float, float, float}  $foreground
     * @param  array{float, float, float}  $background
     */
    private function assertContrast(array $foreground, array $background, string $what): void
    {
        $lighter = max($this->luminance($foreground), $this->luminance($background));
        $darker = min($this->luminance($foreground), $this->luminance($background));
        $ratio = ($lighter + 0.05) / ($darker + 0.05);

        $this->assertGreaterThanOrEqual(4.5, round($ratio, 2), sprintf('%s is %.2f:1', $what, $ratio));
    }

    /**
     * A theme's colour token (the light theme by default) as gamma-encoded sRGB, 0 to 1 per channel.
     *
     * @return array{float, float, float}
     */
    private function token(string $name, string $theme = 'boilerplate'): array
    {
        $this->assertSame(1, preg_match('/--color-'.preg_quote($name, '/').':\s*([^;]+);/', $this->themeBlock($theme), $value), "token --color-{$name} not found in {$theme}");

        if (preg_match('/^#([0-9a-f]{6})$/i', trim($value[1]), $hex)) {
            return array_map(fn (string $pair): float => hexdec($pair) / 255, str_split($hex[1], 2));
        }

        $this->assertSame(1, preg_match('/^oklch\(([\d.]+)%\s+([\d.]+)\s+([\d.]+)\)$/', trim($value[1]), $oklch), "--color-{$name} is neither #rrggbb nor oklch()");

        return $this->oklchToSrgb($oklch[1] / 100, (float) $oklch[2], (float) $oklch[3]);
    }

    private function themeBlock(string $theme): string
    {
        $this->assertSame(1, preg_match('/name: "'.preg_quote($theme, '/').'";(.*?)\n}/s', $this->css(), $block), "the {$theme} theme block is missing");

        return $block[1];
    }

    /**
     * color-mix(in oklab, $a $weight, $b), as the browser does it.
     *
     * @param  array{float, float, float}  $a
     * @param  array{float, float, float}  $b
     * @return array{float, float, float}
     */
    private function mixInOklab(array $a, array $b, float $weight): array
    {
        $oklabA = $this->srgbToOklab($a);
        $oklabB = $this->srgbToOklab($b);

        return $this->oklabToSrgb(...array_map(fn (float $x, float $y): float => $weight * $x + (1 - $weight) * $y, $oklabA, $oklabB));
    }

    /**
     * @param  array{float, float, float}  $rgb
     * @return array{float, float, float}
     */
    private function srgbToOklab(array $rgb): array
    {
        [$red, $green, $blue] = array_map(
            fn (float $channel): float => $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4,
            $rgb,
        );

        $l = (0.4122214708 * $red + 0.5363325363 * $green + 0.0514459929 * $blue) ** (1 / 3);
        $m = (0.2119034982 * $red + 0.6806995451 * $green + 0.1073969566 * $blue) ** (1 / 3);
        $s = (0.0883024619 * $red + 0.2817188376 * $green + 0.6299787005 * $blue) ** (1 / 3);

        return [
            0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s,
            1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s,
            0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s,
        ];
    }

    private function css(): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/resources/css/theme/tokens.css');
    }

    /** @return array{float, float, float} */
    private function oklchToSrgb(float $lightness, float $chroma, float $hue): array
    {
        return $this->oklabToSrgb($lightness, $chroma * cos(deg2rad($hue)), $chroma * sin(deg2rad($hue)));
    }

    /** @return array{float, float, float} */
    private function oklabToSrgb(float $lightness, float $a, float $b): array
    {
        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        $linear = [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];

        return array_map(function (float $channel): float {
            $channel = max(0.0, min(1.0, $channel));

            return $channel <= 0.0031308 ? 12.92 * $channel : 1.055 * $channel ** (1 / 2.4) - 0.055;
        }, $linear);
    }

    /**
     * Text at an opacity, composited over its background as the browser does.
     *
     * @param  array{float, float, float}  $foreground
     * @param  array{float, float, float}  $background
     * @return array{float, float, float}
     */
    private function over(array $foreground, array $background, float $alpha): array
    {
        return array_map(fn (float $front, float $back): float => $alpha * $front + (1 - $alpha) * $back, $foreground, $background);
    }

    /** @param  array{float, float, float}  $rgb */
    private function luminance(array $rgb): float
    {
        [$red, $green, $blue] = array_map(
            fn (float $channel): float => $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4,
            $rgb,
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }
}
