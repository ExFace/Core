<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\ColorPalette;

/**
 * Datatype for color values.
 * 
 * A color value may be an HTML color name (e.g. `DeepSkyBlue`), a hexadecimal value (e.g. `#0a6ed1`
 * or `#abc`) or a CSS color function (e.g. `rgb(10, 110, 209)` or `rgba(10, 110, 209, 0.5)`).
 *  
 * Apart from being a data type model, this class also provides static helpers to work with colors:
 * `parseToRgba()` to turn any of the above notations into numeric channels, `rgbToHsl()` to switch
 * to a perceptual color space and `findColorFamily()`/`isColorInFamily()` to answer the everyday
 * question "is this color basically blue?" without comparing exact values. A family may be narrowed
 * down by a lightness qualifier: `light blue` and `dark blue` are both blue, but only one of them
 * matches `#add8e6`.
 *
 * @author Andrej Kabachnik
 *
 */
class ColorDataType extends AbstractDataType
{
    const FAMILY_RED = 'red';
    
    const FAMILY_ORANGE = 'orange';
    
    const FAMILY_YELLOW = 'yellow';
    
    const FAMILY_GREEN = 'green';
    
    const FAMILY_CYAN = 'cyan';
    
    const FAMILY_BLUE = 'blue';
    
    const FAMILY_PURPLE = 'purple';
    
    const FAMILY_PINK = 'pink';
    
    const FAMILY_BLACK = 'black';
    
    const FAMILY_WHITE = 'white';
    
    const FAMILY_GRAY = 'gray';
    
    const LIGHTNESS_LIGHT = 'light';
    
    const LIGHTNESS_MEDIUM = 'medium';
    
    const LIGHTNESS_DARK = 'dark';
    
    /**
     * Words accepted in front of a family name to narrow it down to a lightness - e.g. `light blue`.
     * 
     * @var array
     */
    const LIGHTNESS_QUALIFIERS = [
        'light' => self::LIGHTNESS_LIGHT,
        'pale' => self::LIGHTNESS_LIGHT,
        'bright' => self::LIGHTNESS_LIGHT,
        'dark' => self::LIGHTNESS_DARK,
        'deep' => self::LIGHTNESS_DARK,
        'medium' => self::LIGHTNESS_MEDIUM
    ];
    
    /**
     * Lightness (as in HSL) at which a color starts to count as `light`.
     * 
     * @var float
     */
    const LIGHTNESS_LIGHT_MIN = 0.65;
    
    /**
     * Lightness (as in HSL) up to which a color counts as `dark`.
     * 
     * @var float
     */
    const LIGHTNESS_DARK_MAX = 0.35;
    
    /**
     * Alternative spellings and near-synonyms accepted as family names.
     * 
     * @var array
     */
    const FAMILY_ALIASES = [
        'grey' => self::FAMILY_GRAY,
        'silver' => self::FAMILY_GRAY,
        'violet' => self::FAMILY_PURPLE,
        'magenta' => self::FAMILY_PURPLE,
        'fuchsia' => self::FAMILY_PURPLE,
        'turquoise' => self::FAMILY_CYAN,
        'aqua' => self::FAMILY_CYAN,
        'teal' => self::FAMILY_CYAN
    ];
    
    /**
     * Hue ranges (in degrees, upper bound exclusive) of the color families.
     * 
     * Red wraps around 0°, so it is matched separately - see findColorFamily().
     * 
     * @var array
     */
    const FAMILY_HUE_RANGES = [
        self::FAMILY_ORANGE => [16, 45],
        self::FAMILY_YELLOW => [45, 66],
        self::FAMILY_GREEN => [66, 165],
        self::FAMILY_CYAN => [165, 190],
        self::FAMILY_BLUE => [190, 260],
        self::FAMILY_PURPLE => [260, 310],
        self::FAMILY_PINK => [310, 345]
    ];
    
    /**
     * Hexadecimal values of all HTML/CSS color names.
     * 
     * @var array
     */
    const COLOR_NAMES = [
        'aliceblue' => '#f0f8ff', 'antiquewhite' => '#faebd7', 'aqua' => '#00ffff',
        'aquamarine' => '#7fffd4', 'azure' => '#f0ffff', 'beige' => '#f5f5dc',
        'bisque' => '#ffe4c4', 'black' => '#000000', 'blanchedalmond' => '#ffebcd',
        'blue' => '#0000ff', 'blueviolet' => '#8a2be2', 'brown' => '#a52a2a',
        'burlywood' => '#deb887', 'cadetblue' => '#5f9ea0', 'chartreuse' => '#7fff00',
        'chocolate' => '#d2691e', 'coral' => '#ff7f50', 'cornflowerblue' => '#6495ed',
        'cornsilk' => '#fff8dc', 'crimson' => '#dc143c', 'cyan' => '#00ffff',
        'darkblue' => '#00008b', 'darkcyan' => '#008b8b', 'darkgoldenrod' => '#b8860b',
        'darkgray' => '#a9a9a9', 'darkgrey' => '#a9a9a9', 'darkgreen' => '#006400',
        'darkkhaki' => '#bdb76b', 'darkmagenta' => '#8b008b', 'darkolivegreen' => '#556b2f',
        'darkorange' => '#ff8c00', 'darkorchid' => '#9932cc', 'darkred' => '#8b0000',
        'darksalmon' => '#e9967a', 'darkseagreen' => '#8fbc8f', 'darkslateblue' => '#483d8b',
        'darkslategray' => '#2f4f4f', 'darkslategrey' => '#2f4f4f', 'darkturquoise' => '#00ced1',
        'darkviolet' => '#9400d3', 'deeppink' => '#ff1493', 'deepskyblue' => '#00bfff',
        'dimgray' => '#696969', 'dimgrey' => '#696969', 'dodgerblue' => '#1e90ff',
        'firebrick' => '#b22222', 'floralwhite' => '#fffaf0', 'forestgreen' => '#228b22',
        'fuchsia' => '#ff00ff', 'gainsboro' => '#dcdcdc', 'ghostwhite' => '#f8f8ff',
        'gold' => '#ffd700', 'goldenrod' => '#daa520', 'gray' => '#808080',
        'grey' => '#808080', 'green' => '#008000', 'greenyellow' => '#adff2f',
        'honeydew' => '#f0fff0', 'hotpink' => '#ff69b4', 'indianred' => '#cd5c5c',
        'indigo' => '#4b0082', 'ivory' => '#fffff0', 'khaki' => '#f0e68c',
        'lavender' => '#e6e6fa', 'lavenderblush' => '#fff0f5', 'lawngreen' => '#7cfc00',
        'lemonchiffon' => '#fffacd', 'lightblue' => '#add8e6', 'lightcoral' => '#f08080',
        'lightcyan' => '#e0ffff', 'lightgoldenrodyellow' => '#fafad2', 'lightgray' => '#d3d3d3',
        'lightgrey' => '#d3d3d3', 'lightgreen' => '#90ee90', 'lightpink' => '#ffb6c1',
        'lightsalmon' => '#ffa07a', 'lightseagreen' => '#20b2aa', 'lightskyblue' => '#87cefa',
        'lightslategray' => '#778899', 'lightslategrey' => '#778899', 'lightsteelblue' => '#b0c4de',
        'lightyellow' => '#ffffe0', 'lime' => '#00ff00', 'limegreen' => '#32cd32',
        'linen' => '#faf0e6', 'magenta' => '#ff00ff', 'maroon' => '#800000',
        'mediumaquamarine' => '#66cdaa', 'mediumblue' => '#0000cd', 'mediumorchid' => '#ba55d3',
        'mediumpurple' => '#9370db', 'mediumseagreen' => '#3cb371', 'mediumslateblue' => '#7b68ee',
        'mediumspringgreen' => '#00fa9a', 'mediumturquoise' => '#48d1cc', 'mediumvioletred' => '#c71585',
        'midnightblue' => '#191970', 'mintcream' => '#f5fffa', 'mistyrose' => '#ffe4e1',
        'moccasin' => '#ffe4b5', 'navajowhite' => '#ffdead', 'navy' => '#000080',
        'oldlace' => '#fdf5e6', 'olive' => '#808000', 'olivedrab' => '#6b8e23',
        'orange' => '#ffa500', 'orangered' => '#ff4500', 'orchid' => '#da70d6',
        'palegoldenrod' => '#eee8aa', 'palegreen' => '#98fb98', 'paleturquoise' => '#afeeee',
        'palevioletred' => '#db7093', 'papayawhip' => '#ffefd5', 'peachpuff' => '#ffdab9',
        'peru' => '#cd853f', 'pink' => '#ffc0cb', 'plum' => '#dda0dd',
        'powderblue' => '#b0e0e6', 'purple' => '#800080', 'rebeccapurple' => '#663399',
        'red' => '#ff0000', 'rosybrown' => '#bc8f8f', 'royalblue' => '#4169e1',
        'saddlebrown' => '#8b4513', 'salmon' => '#fa8072', 'sandybrown' => '#f4a460',
        'seagreen' => '#2e8b57', 'seashell' => '#fff5ee', 'sienna' => '#a0522d',
        'silver' => '#c0c0c0', 'skyblue' => '#87ceeb', 'slateblue' => '#6a5acd',
        'slategray' => '#708090', 'slategrey' => '#708090', 'snow' => '#fffafa',
        'springgreen' => '#00ff7f', 'steelblue' => '#4682b4', 'tan' => '#d2b48c',
        'teal' => '#008080', 'thistle' => '#d8bfd8', 'tomato' => '#ff6347',
        'turquoise' => '#40e0d0', 'violet' => '#ee82ee', 'wheat' => '#f5deb3',
        'white' => '#ffffff', 'whitesmoke' => '#f5f5f5', 'yellow' => '#ffff00',
        'yellowgreen' => '#9acd32'
    ];
    
    private $colorPresets = [];

    public function getColorPresets(): array
    {
        return $this->colorPresets;
    }

    /**
     * Define the color presets to display in a color palette when editing the attribute of this datatype.
     *
     * @uxon-property color_presets
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param array $colorPresets
     * @return ColorDataType
     */
    public function setColorPresets(UxonObject $colorPresets): ColorDataType
    {
        $this->colorPresets = $colorPresets->toArray();
        return $this;
    }
    
    /**
     * Turns any supported color notation into numeric channels `[red, green, blue, alpha]` or NULL if unparsable.
     * 
     * Accepts HTML color names (`DeepSkyBlue`), hex values with 3, 4, 6 or 8 digits (`#abc`, `#0a6ed1`)
     * and the CSS functions `rgb()`/`rgba()`. Red, green and blue are returned as integers `0-255`,
     * alpha as a float `0-1` (`1` if the notation does not carry an alpha channel). The keyword
     * `transparent` yields an alpha of `0`, so callers can tell "no color" from "black".
     * 
     * @param string $color
     * @return array|NULL [int $red, int $green, int $blue, float $alpha]
     */
    public static function parseToRgba(string $color) : ?array
    {
        $color = trim($color);
        if ($color === '') {
            return null;
        }
        
        $lower = mb_strtolower($color);
        if ($lower === 'transparent') {
            return [0, 0, 0, 0.0];
        }
        if (array_key_exists($lower, self::COLOR_NAMES)) {
            $color = self::COLOR_NAMES[$lower];
        }
        
        if (substr($color, 0, 1) === '#') {
            $hex = substr($color, 1);
            // Expand the short notations #rgb and #rgba to their long counterparts before parsing,
            // so that there is only a single place converting hex digits to channel values.
            if (strlen($hex) === 3 || strlen($hex) === 4) {
                $expanded = '';
                foreach (str_split($hex) as $digit) {
                    $expanded .= $digit . $digit;
                }
                $hex = $expanded;
            }
            if (! preg_match('/^[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $hex)) {
                return null;
            }
            return [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2)),
                strlen($hex) === 8 ? round(hexdec(substr($hex, 6, 2)) / 255, 4) : 1.0
            ];
        }
        
        // rgb(10, 110, 209) / rgba(10 110 209 / 50%) and everything in between: the separators are
        // matched loosely on purpose - browsers serialize computed styles in several of these forms.
        if (preg_match('/^rgba?\s*\(([^\)]*)\)$/i', $color, $matches) === 1) {
            $parts = preg_split('/[\s,\/]+/', trim($matches[1]), -1, PREG_SPLIT_NO_EMPTY);
            if (count($parts) < 3) {
                return null;
            }
            $channels = [];
            for ($i = 0; $i < 3; $i++) {
                $part = $parts[$i];
                if (substr($part, -1) === '%') {
                    $channels[] = (int) round(((float) substr($part, 0, -1)) * 255 / 100);
                } else {
                    $channels[] = (int) round((float) $part);
                }
            }
            $alpha = 1.0;
            if (isset($parts[3])) {
                $alpha = substr($parts[3], -1) === '%' ? ((float) substr($parts[3], 0, -1)) / 100 : (float) $parts[3];
            }
            $channels[] = $alpha;
            return $channels;
        }
        
        return null;
    }
    
    /**
     * Converts RGB channels to `[hue, saturation, lightness]` - hue in degrees `0-360`, the rest as floats `0-1`.
     * 
     * Color families cannot be told apart in the RGB cube: `#0a6ed1` and `#4a9bff` are both blue, yet
     * every one of their channels differs. In HSL the same question is a single comparison of the hue,
     * which is why every family check goes through this conversion first.
     * 
     * @param int $red
     * @param int $green
     * @param int $blue
     * @return array [float $hue, float $saturation, float $lightness]
     */
    public static function rgbToHsl(int $red, int $green, int $blue) : array
    {
        $r = $red / 255;
        $g = $green / 255;
        $b = $blue / 255;
        
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        $lightness = ($max + $min) / 2;
        
        if ($delta == 0) {
            return [0.0, 0.0, $lightness];
        }
        
        $saturation = $lightness > 0.5 ? $delta / (2 - $max - $min) : $delta / ($max + $min);
        
        switch (true) {
            case $max === $r: $hue = 60 * fmod((($g - $b) / $delta), 6); break;
            case $max === $g: $hue = 60 * ((($b - $r) / $delta) + 2); break;
            default: $hue = 60 * ((($r - $g) / $delta) + 4);
        }
        if ($hue < 0) {
            $hue += 360;
        }
        
        return [$hue, $saturation, $lightness];
    }
    
    /**
     * Returns the name of the color family the given color belongs to - e.g. `blue` for `#0a6ed1`.
     * 
     * Returns one of the `FAMILY_xxx` constants or NULL if the color cannot be parsed or is fully
     * transparent. Use this instead of comparing color values whenever the exact shade does not
     * matter - a facade, a theme or a browser may all render "blue" as a different value.
     * 
     * Colors with little saturation carry no meaningful hue, so they are classified by their
     * lightness as `black`, `white` or `gray` instead.
     * 
     * @param string $color
     * @return string|NULL
     */
    public static function findColorFamily(string $color) : ?string
    {
        $rgba = self::parseToRgba($color);
        if ($rgba === null || $rgba[3] <= 0) {
            return null;
        }
        
        list($hue, $saturation, $lightness) = self::rgbToHsl($rgba[0], $rgba[1], $rgba[2]);
        
        if ($saturation < 0.12) {
            switch (true) {
                case $lightness < 0.12: return self::FAMILY_BLACK;
                case $lightness > 0.92: return self::FAMILY_WHITE;
                default: return self::FAMILY_GRAY;
            }
        }
        
        foreach (self::FAMILY_HUE_RANGES as $family => $range) {
            if ($hue >= $range[0] && $hue < $range[1]) {
                return $family;
            }
        }
        
        // Pale reddish colors like `Pink` or `LightPink` sit just below 360°, where `Crimson` lives
        // too. Hue alone cannot separate them - what makes them pink is that they are much lighter.
        if ($hue >= 345 && $lightness > 0.75) {
            return self::FAMILY_PINK;
        }
        
        // Red is the only family wrapping around 0°, so it is what remains once all other ranges failed.
        return self::FAMILY_RED;
    }
    
    /**
     * Checks if the given color belongs to the given color family - e.g. `#0a6ed1` is in the family `blue`.
     * 
     * The family may be
     * 
     * - one of the `FAMILY_xxx` constants or one of their aliases (`grey`, `violet`, `magenta`,
     * `turquoise`, ...),
     * - any of these with a lightness qualifier in front (`light blue`, `dark green`, `pale red`,
     * and also written as a single word: `lightblue`),
     * - any HTML color name or hex value, in which case the family of that reference color is used -
     * so `isColorInFamily('#0a6ed1', 'DodgerBlue')` is TRUE as well.
     * 
     * @param string $color
     * @param string $family
     * @return bool
     */
    public static function isColorInFamily(string $color, string $family) : bool
    {
        $actual = self::findColorFamily($color);
        if ($actual === null) {
            return false;
        }
        
        $expected = self::parseColorSpec($family);
        if ($expected === null || $actual !== $expected['family']) {
            return false;
        }
        // No qualifier means every shade of the family is acceptable.
        if ($expected['lightness'] === null) {
            return true;
        }
        
        return self::findColorLightness($color) === $expected['lightness'];
    }
    
    /**
     * Splits something a user may call a color into a family and an optional lightness qualifier.
     * 
     * Returns `['family' => 'blue', 'lightness' => 'light', 'name' => 'light blue']` for `light blue`
     * and `['family' => 'blue', 'lightness' => null, 'name' => 'blue']` for `#0a6ed1`. Returns NULL
     * if the input is not a color at all - callers should treat that as a configuration error rather
     * than as "does not match", because a typo would otherwise silently fail every comparison.
     * 
     * WHY A QUALIFIER IS ONLY SPLIT OFF IF THE REMAINDER IS A FAMILY: `lightseagreen` is an HTML
     * color name, not "a light seagreen" - and its actual value is not light at all. Only splitting
     * when what remains is a real family name (`lightblue` -> `light` + `blue`) keeps every HTML
     * color name resolvable to the family of its own value.
     * 
     * @param string $spec
     * @return array|NULL ['family' => string, 'lightness' => string|null, 'name' => string]
     */
    public static function parseColorSpec(string $spec) : ?array
    {
        $spec = mb_strtolower(trim($spec));
        if ($spec === '') {
            return null;
        }
        
        foreach (self::LIGHTNESS_QUALIFIERS as $qualifier => $lightness) {
            if (mb_strpos($spec, $qualifier) !== 0) {
                continue;
            }
            $rest = trim(substr($spec, strlen($qualifier)), " \t-_");
            $family = self::findFamilyByName($rest, false);
            if ($family !== null) {
                return [
                    'family' => $family,
                    'lightness' => $lightness,
                    'name' => $lightness . ' ' . $family
                ];
            }
        }
        
        $family = self::findFamilyByName($spec);
        if ($family === null) {
            return null;
        }
        return [
            'family' => $family,
            'lightness' => null,
            'name' => $family
        ];
    }
    
    /**
     * Tells whether a color is `light`, `medium` or `dark` - or NULL if it cannot be parsed.
     * 
     * This is what makes `light blue` distinguishable from `dark blue`: both share the hue, they
     * only differ in how much white or black is mixed in.
     * 
     * @param string $color
     * @return string|NULL
     */
    public static function findColorLightness(string $color) : ?string
    {
        $rgba = self::parseToRgba($color);
        if ($rgba === null || $rgba[3] <= 0) {
            return null;
        }
        
        $lightness = self::rgbToHsl($rgba[0], $rgba[1], $rgba[2])[2];
        switch (true) {
            case $lightness >= self::LIGHTNESS_LIGHT_MIN: return self::LIGHTNESS_LIGHT;
            case $lightness <= self::LIGHTNESS_DARK_MAX: return self::LIGHTNESS_DARK;
            default: return self::LIGHTNESS_MEDIUM;
        }
    }
    
    /**
     * Normalizes anything a user may call a color family into one of the `FAMILY_xxx` constants.
     * 
     * WHY IT ALSO ACCEPTS CONCRETE COLORS: family names and color names overlap (`blue` is both),
     * and callers reading a color from a model or a test scenario cannot know which one they got.
     * Resolving both here means no caller has to make that distinction. Pass `$allowColorValues`
     * as FALSE to only accept real family names - lightness qualifiers rely on that to tell
     * `light blue` (a qualified family) from `lightseagreen` (a color name).
     * 
     * Note: this ignores any lightness qualifier, so `dark blue` yields `blue`. Use
     * parseColorSpec() if the qualifier matters.
     * 
     * @param string $name
     * @param bool $allowColorValues
     * @return string|NULL
     */
    public static function findFamilyByName(string $name, bool $allowColorValues = true) : ?string
    {
        $name = mb_strtolower(trim($name));
        if (array_key_exists($name, self::FAMILY_ALIASES)) {
            return self::FAMILY_ALIASES[$name];
        }
        if (array_key_exists($name, self::FAMILY_HUE_RANGES)
            || $name === self::FAMILY_RED
            || $name === self::FAMILY_BLACK
            || $name === self::FAMILY_WHITE
            || $name === self::FAMILY_GRAY) {
            return $name;
        }
        return $allowColorValues ? self::findColorFamily($name) : null;
    }
}