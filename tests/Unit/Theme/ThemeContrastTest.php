<?php

namespace Tests\Unit\Theme;

use PHPUnit\Framework\TestCase;

/**
 * The light theme's text tokens clear WCAG AA (4.5:1), read straight from
 * resources/css/theme/tokens.css so a later tweak can't quietly undo it
 * (GitHub #31).
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
     * A light-theme colour token as gamma-encoded sRGB, 0 to 1 per channel.
     *
     * @return array{float, float, float}
     */
    private function token(string $name): array
    {
        $this->assertSame(1, preg_match('/name: "boilerplate";(.*?)\n}/s', $this->css(), $theme), 'the light theme block is missing');
        $this->assertSame(1, preg_match('/--color-'.preg_quote($name, '/').':\s*([^;]+);/', $theme[1], $value), "token --color-{$name} not found");

        if (preg_match('/^#([0-9a-f]{6})$/i', trim($value[1]), $hex)) {
            return array_map(fn (string $pair): float => hexdec($pair) / 255, str_split($hex[1], 2));
        }

        $this->assertSame(1, preg_match('/^oklch\(([\d.]+)%\s+([\d.]+)\s+([\d.]+)\)$/', trim($value[1]), $oklch), "--color-{$name} is neither #rrggbb nor oklch()");

        return $this->oklchToSrgb($oklch[1] / 100, (float) $oklch[2], (float) $oklch[3]);
    }

    private function css(): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/resources/css/theme/tokens.css');
    }

    /** @return array{float, float, float} */
    private function oklchToSrgb(float $lightness, float $chroma, float $hue): array
    {
        $a = $chroma * cos(deg2rad($hue));
        $b = $chroma * sin(deg2rad($hue));
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
