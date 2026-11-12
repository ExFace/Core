<?php
namespace exface\Core\CommonLogic\Utils;

use exface\Core\CommonLogic\Constants\Colors;

/**
 * Provides server-side color transformations equivalent to exfColorTools.js.
 *
 * Browser-specific CSS values such as CSS variables cannot be resolved on the server. Hexadecimal,
 * RGB(A), and named HTML colors are supported.
 */
abstract class ColorTools
{
    /**
     * Shifts the HSL brightness of a CSS color and returns a six-digit hexadecimal color.
     *
     * Invalid colors are returned unchanged, matching the JavaScript helper's fallback behavior.
     */
    public static function shadeCssColor(string $baseColor, float $deltaLightness): string
    {
        $rgba = static::cssColorToRgba($baseColor);
        if ($rgba === null) {
            return $baseColor;
        }

        $hsl = static::rgbToHsl($rgba);
        $hsl['l'] = min(1.0, max(0.0, $hsl['l'] + $deltaLightness));

        return static::rgbaToHex(static::hslToRgb($hsl));
    }

    /**
     * Chooses black or white text using the weighted WCAG contrast of a background color.
     *
     * A weight of `0` always chooses white, while `1` always chooses black. Invalid or empty
     * background colors use black, matching the JavaScript helper's fallback behavior.
     *
     * Transparent and semi-transparent backgrounds are alpha-blended over `$backdropCssColor`
     * (the color assumed to be visible underneath the element) before measuring contrast.
     * If no valid opaque backdrop is given, white is assumed.
     */
    public static function pickTextColorForBackgroundColor(?string $backgroundCssColor, ?float $weight = null, ?string $backdropCssColor = null): string
    {
        if ($backgroundCssColor === null || $backgroundCssColor === '') {
            return '#000';
        }
        $rgba = static::cssColorToRgba($backgroundCssColor);
        if ($rgba === null) {
            return '#000';
        }
        if ($rgba['a'] < 1.0) {
            $rgba = static::blendOverBackdrop($rgba, $backdropCssColor);
        }

        $backgroundLuminance = static::relativeLuminance($rgba);
        $contrastToWhite = static::contrastRatio(1.0, $backgroundLuminance);
        $contrastToBlack = static::contrastRatio($backgroundLuminance, 0.0);
        $weight = $weight === null ? 0.5 : max(0.0, min(1.0, $weight));
        // Slightly bias towards white to match the JavaScript helper's behavior:
        $weight = $weight > 0.0 ? $weight - 0.01 : 0.0; 

        return $weight * $contrastToBlack >= (1 - $weight) * $contrastToWhite ? '#000' : '#fff';
    }

    /**
     * Alpha-blends a (semi-)transparent color over an opaque backdrop color.
     *
     * Falls back to a white backdrop if the given one is empty, invalid, or not fully opaque.
     *
     * @param array{r:int,g:int,b:int,a:float} $rgba
     * @return array{r:int,g:int,b:int,a:float}
     */
    private static function blendOverBackdrop(array $rgba, ?string $backdropCssColor): array
    {
        $backdrop = ($backdropCssColor !== null && $backdropCssColor !== '')
            ? static::cssColorToRgba($backdropCssColor)
            : null;
        if ($backdrop === null) {
            $backdrop = ['r' => 255, 'g' => 255, 'b' => 255];
        }
        
        // Ensure the backdrop is fully opaque, since the blending formula assumes it is.
        $backdrop['a'] = 1.0;

        // Source-over compositing on an opaque backdrop is a lerp weighted by the source alpha
        return static::lerpRgba($backdrop, array_merge($rgba, ['a' => 1.0]), $rgba['a']);
    }

    /**
     * Linearly interpolates between two RGBA colors, channel by channel (including alpha).
     *
     * `$t = 0` returns `$from`, `$t = 1` returns `$to`; values outside `[0..1]` are clamped.
     * Interpolation happens in sRGB space, which matches how browsers composite colors.
     *
     * @param array{r:int,g:int,b:int,a?:float} $from
     * @param array{r:int,g:int,b:int,a?:float} $to
     * @return array{r:int,g:int,b:int,a:float}
     */
    public static function lerpRgba(array $from, array $to, float $t): array
    {
        $t = max(0.0, min(1.0, $t));
        $lerp = static fn(float $a, float $b): float => $a + ($b - $a) * $t;

        return [
            'r' => (int) round($lerp($from['r'], $to['r'])),
            'g' => (int) round($lerp($from['g'], $to['g'])),
            'b' => (int) round($lerp($from['b'], $to['b'])),
            'a' => $lerp($from['a'] ?? 1.0, $to['a'] ?? 1.0),
        ];
    }

    /**
     * Converts supported CSS color notation to red, green, blue, and alpha components.
     *
     * @return array{r:int,g:int,b:int,a:float}|null
     */
    private static function cssColorToRgba(string $color): ?array
    {
        $color = trim($color);
        if (strcasecmp($color, 'transparent') === 0) {
            return ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0.0];
        }
        if (preg_match(
            '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*([.\d]+))?\s*\)$/i',
            $color,
            $matches
        ) === 1) {
            $red = (int) $matches[1];
            $green = (int) $matches[2];
            $blue = (int) $matches[3];
            $alpha = isset($matches[4]) ? (float) $matches[4] : 1.0;
            if ($red > 255 || $green > 255 || $blue > 255 || $alpha < 0 || $alpha > 1) {
                return null;
            }
            return ['r' => $red, 'g' => $green, 'b' => $blue, 'a' => $alpha];
        }

        try {
            $hex = ltrim(Colors::toHex($color), '#');
        } catch (\UnexpectedValueException $exception) {
            return null;
        }
        if (preg_match('/^[0-9a-f]{3,4}$/i', $hex) === 1) {
            $hex = implode('', array_map(static fn(string $digit): string => $digit . $digit, str_split($hex)));
        }
        if (preg_match('/^[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $hex) !== 1) {
            return null;
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
            'a' => strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0,
        ];
    }

    /**
     * Converts RGB components to an HSL color with components in the range from zero to one.
     *
     * @param array{r:int,g:int,b:int} $rgb
     * @return array{h:float,s:float,l:float}
     */
    private static function rgbToHsl(array $rgb): array
    {
        $red = $rgb['r'] / 255;
        $green = $rgb['g'] / 255;
        $blue = $rgb['b'] / 255;
        $maximum = max($red, $green, $blue);
        $minimum = min($red, $green, $blue);
        $hue = 0.0;
        $saturation = 0.0;
        $lightness = ($maximum + $minimum) / 2;

        if ($maximum !== $minimum) {
            $difference = $maximum - $minimum;
            $saturation = $lightness > 0.5
                ? $difference / (2 - $maximum - $minimum)
                : $difference / ($maximum + $minimum);
            if ($maximum === $red) {
                $hue = ($green - $blue) / $difference + ($green < $blue ? 6 : 0);
            } elseif ($maximum === $green) {
                $hue = ($blue - $red) / $difference + 2;
            } else {
                $hue = ($red - $green) / $difference + 4;
            }
            $hue /= 6;
        }

        return ['h' => $hue, 's' => $saturation, 'l' => $lightness];
    }

    /**
     * Converts HSL components in the range from zero to one to RGB components.
     *
     * @param array{h:float,s:float,l:float} $hsl
     * @return array{r:int,g:int,b:int}
     */
    private static function hslToRgb(array $hsl): array
    {
        if ($hsl['s'] === 0.0) {
            $red = $green = $blue = $hsl['l'];
        } else {
            $q = $hsl['l'] < 0.5
                ? $hsl['l'] * (1 + $hsl['s'])
                : $hsl['l'] + $hsl['s'] - $hsl['l'] * $hsl['s'];
            $p = 2 * $hsl['l'] - $q;
            $red = static::hueToRgb($p, $q, $hsl['h'] + 1 / 3);
            $green = static::hueToRgb($p, $q, $hsl['h']);
            $blue = static::hueToRgb($p, $q, $hsl['h'] - 1 / 3);
        }

        return [
            'r' => (int) round($red * 255),
            'g' => (int) round($green * 255),
            'b' => (int) round($blue * 255),
        ];
    }

    /**
     * Converts one HSL hue channel to its RGB value.
     */
    private static function hueToRgb(float $p, float $q, float $hue): float
    {
        if ($hue < 0) {
            $hue++;
        }
        if ($hue > 1) {
            $hue--;
        }
        if ($hue < 1 / 6) {
            return $p + ($q - $p) * 6 * $hue;
        }
        if ($hue < 1 / 2) {
            return $q;
        }
        if ($hue < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $hue) * 6;
        }
        return $p;
    }

    /**
     * Converts RGB components to a six-digit hexadecimal CSS color.
     *
     * @param array{r:int,g:int,b:int} $rgb
     */
    private static function rgbaToHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
    }

    /**
     * Converts an sRGB channel in the range from zero to one to linear light.
     */
    private static function srgbToLinear(float $channel): float
    {
        return $channel <= 0.03928
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }

    /**
     * Calculates WCAG relative luminance for RGB components.
     *
     * @param array{r:int,g:int,b:int} $rgb
     */
    private static function relativeLuminance(array $rgb): float
    {
        $red = static::srgbToLinear($rgb['r'] / 255);
        $green = static::srgbToLinear($rgb['g'] / 255);
        $blue = static::srgbToLinear($rgb['b'] / 255);

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    /**
     * Calculates the WCAG contrast ratio between two relative luminance values.
     */
    private static function contrastRatio(float $first, float $second): float
    {
        $highest = max($first, $second);
        $lowest = min($first, $second);

        return ($highest + 0.05) / ($lowest + 0.05);
    }
}