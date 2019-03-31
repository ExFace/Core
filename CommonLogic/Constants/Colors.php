<?php

namespace exface\Core\CommonLogic\Constants;

/**
 * Color constants for basic colors supported by all facades.
 * 
 * These are default HTML color names. See the link below for preview and
 * official definition.
 * 
 * @link https://www.w3schools.com/colors/colors_groups.asp
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class Colors 
{
    // Default color to make the facade pick a color
    const DEFAULT_COLOR = '';
    // No-color-color to tell a facade that something is transparent
    const NONE = null;
    
    // Pink Colors
    const PINK = 'Pink';
    const PINK_LIGHTPINK = 'LightPink';
    const PINK_HOTPINK = 'HotPink';
    const PINK_DEEPPINK = 'DeepPink';
    const PINK_PALEVIOLETRED = 'PaleVioletRed';
    const PINK_MEDIUMVIOLETRED = 'MediumVioletRed';
    // Purple Colors
    const PURPLE_LAVENDER = 'Lavender';
    const PURPLE_THISTLE = 'Thistle';
    const PURPLE_PLUM = 'Plum';
    const PURPLE_ORCHID = 'Orchid';
    const PURPLE_VIOLET = 'Violet';
    const PURPLE_FUCHSIA = 'Fuchsia';
    const PURPLE_MAGENTA = 'Magenta';
    const PURPLE_MEDIUMORCHID = 'MediumOrchid';
    const PURPLE_DARKORCHID = 'DarkOrchid';
    const PURPLE_DARKVIOLET = 'DarkViolet';
    const PURPLE_BLUEVIOLET = 'BlueViolet';
    const PURPLE_DARKMAGENTA = 'DarkMagenta';
    const PURPLE = 'Purple';
    const PURPLE_MEDIUMPURPLE = 'MediumPurple';
    const PURPLE_MEDIUMSLATEBLUE = 'MediumSlateBlue';
    const PURPLE_SLATEBLUE = 'SlateBlue';
    const PURPLE_DARKSLATEBLUE = 'DarkSlateBlue';
    const PURPLE_REBECCAPURPLE = 'RebeccaPurple';
    const PURPLE_INDIGO  = 'Indigo ';
    // Red Colors
    const RED_LIGHTSALMON = 'LightSalmon';
    const RED_SALMON = 'Salmon';
    const RED_DARKSALMON = 'DarkSalmon';
    const RED_LIGHTCORAL = 'LightCoral';
    const RED_INDIANRED  = 'IndianRed ';
    const RED_CRIMSON = 'Crimson';
    const RED = 'Red';
    const RED_FIREBRICK = 'FireBrick';
    const RED_DARKRED = 'DarkRed';
    // Orange Colors
    const ORANGE = 'Orange';
    const ORANGE_DARKORANGE = 'DarkOrange';
    const ORANGE_CORAL = 'Coral';
    const ORANGE_TOMATO = 'Tomato';
    const ORANGE_ORANGERED = 'OrangeRed';
    // Yellow Colors
    const YELLOW_GOLD = 'Gold';
    const YELLOW = 'Yellow';
    const YELLOW_LIGHTYELLOW = 'LightYellow';
    const YELLOW_LEMONCHIFFON = 'LemonChiffon';
    const YELLOW_LIGHTGOLDENRODYELLOW = 'LightGoldenRodYellow';
    const YELLOW_PAPAYAWHIP = 'PapayaWhip';
    const YELLOW_MOCCASIN = 'Moccasin';
    const YELLOW_PEACHPUFF = 'PeachPuff';
    const YELLOW_PALEGOLDENROD = 'PaleGoldenRod';
    const YELLOW_KHAKI = 'Khaki';
    const YELLOW_DARKKHAKI = 'DarkKhaki';
    // Green Colors
    const GREEN_GREENYELLOW = 'GreenYellow';
    const GREEN_CHARTREUSE = 'Chartreuse';
    const GREEN_LAWNGREEN = 'LawnGreen';
    const GREEN_LIME = 'Lime';
    const GREEN_LIMEGREEN = 'LimeGreen';
    const GREEN_PALEGREEN = 'PaleGreen';
    const GREEN_LIGHTGREEN = 'LightGreen';
    const GREEN_MEDIUMSPRINGGREEN = 'MediumSpringGreen';
    const GREEN_SPRINGGREEN = 'SpringGreen';
    const GREEN_MEDIUMSEAGREEN = 'MediumSeaGreen';
    const GREEN_SEAGREEN = 'SeaGreen';
    const GREEN_FORESTGREEN = 'ForestGreen';
    const GREEN = 'Green';
    const GREEN_DARKGREEN = 'DarkGreen';
    const GREEN_YELLOWGREEN = 'YellowGreen';
    const GREEN_OLIVEDRAB = 'OliveDrab';
    const GREEN_DARKOLIVEGREEN = 'DarkOliveGreen';
    const GREEN_MEDIUMAQUAMARINE = 'MediumAquaMarine';
    const GREEN_DARKSEAGREEN = 'DarkSeaGreen';
    const GREEN_LIGHTSEAGREEN = 'LightSeaGreen';
    const GREEN_DARKCYAN = 'DarkCyan';
    const GREEN_TEAL = 'Teal';
    // Cyan colors
    const CYAN_AQUA = 'Aqua';
    const CYAN = 'Cyan';
    const CYAN_LIGHTCYAN = 'LightCyan';
    const CYAN_PALETURQUOISE = 'PaleTurquoise';
    const CYAN_AQUAMARINE = 'Aquamarine';
    const CYAN_TURQUOISE = 'Turquoise';
    const CYAN_MEDIUMTURQUOISE = 'MediumTurquoise';
    const CYAN_DARKTURQUOISE = 'DarkTurquoise';
    // Blue Colors
    const BLUE_CADETBLUE = 'CadetBlue';
    const BLUE_STEELBLUE = 'SteelBlue';
    const BLUE_LIGHTSTEELBLUE = 'LightSteelBlue';
    const BLUE_LIGHTBLUE = 'LightBlue';
    const BLUE_POWDERBLUE = 'PowderBlue';
    const BLUE_LIGHTSKYBLUE = 'LightSkyBlue';
    const BLUE_SKYBLUE = 'SkyBlue';
    const BLUE_CORNFLOWERBLUE = 'CornflowerBlue';
    const BLUE_DEEPSKYBLUE = 'DeepSkyBlue';
    const BLUE_DODGERBLUE = 'DodgerBlue';
    const BLUE_ROYALBLUE = 'RoyalBlue';
    const BLUE = 'Blue';
    const BLUE_MEDIUMBLUE = 'MediumBlue';
    const BLUE_DARKBLUE = 'DarkBlue';
    const BLUE_NAVY = 'Navy';
    const BLUE_MIDNIGHTBLUE = 'MidnightBlue';
    // Brown Colors
    const BROWN_CORNSILK = 'Cornsilk';
    const BROWN_BLANCHEDALMOND = 'BlanchedAlmond';
    const BROWN_BISQUE = 'Bisque';
    const BROWN_NAVAJOWHITE = 'NavajoWhite';
    const BROWN_WHEAT = 'Wheat';
    const BROWN_BURLYWOOD = 'BurlyWood';
    const BROWN_TAN = 'Tan';
    const BROWN_ROSYBROWN = 'RosyBrown';
    const BROWN_SANDYBROWN = 'SandyBrown';
    const BROWN_GOLDENROD = 'GoldenRod';
    const BROWN_DARKGOLDENROD = 'DarkGoldenRod';
    const BROWN_PERU = 'Peru';
    const BROWN_CHOCOLATE = 'Chocolate';
    const BROWN_OLIVE = 'Olive';
    const BROWN_SADDLEBROWN = 'SaddleBrown';
    const BROWN_SIENNA = 'Sienna';
    const BROWN = 'Brown';
    const BROWN_MAROON = 'Maroon';
    // White colors
    const WHITE = 'White';
    const WHITE_SNOW = 'Snow';
    const WHITE_HONEYDEW = 'HoneyDew';
    const WHITE_MINTCREAM = 'MintCream';
    const WHITE_AZURE = 'Azure';
    const WHITE_ALICEBLUE = 'AliceBlue';
    const WHITE_GHOSTWHITE = 'GhostWhite';
    const WHITE_WHITESMOKE = 'WhiteSmoke';
    const WHITE_SEASHELL = 'SeaShell';
    const WHITE_BEIGE = 'Beige';
    const WHITE_OLDLACE = 'OldLace';
    const WHITE_FLORALWHITE = 'FloralWhite';
    const WHITE_IVORY = 'Ivory';
    const WHITE_ANTIQUEWHITE = 'AntiqueWhite';
    const WHITE_LINEN = 'Linen';
    const WHITE_LAVENDERBLUSH = 'LavenderBlush';
    const WHITE_MISTYROSE = 'MistyRose';
    // Grey Colors
    const GREY_GAINSBORO = 'Gainsboro';
    const GREY_LIGHTGRAY = 'LightGray';
    const GREY_SILVER = 'Silver';
    const GREY_DARKGRAY = 'DarkGray';
    const GREY_DIMGRAY = 'DimGray';
    const GREY_GRAY = 'Gray';
    const GREY_LIGHTSLATEGRAY = 'LightSlateGray';
    const GREY_SLATEGRAY = 'SlateGray';
    const GREY_DARKSLATEGRAY = 'DarkSlateGray';
    const BLACK = 'Black';
    
    /**
     * Returns TRUE if the given HTML color is dark and FALSE otherwise.
     * 
     * @param string $color
     * @return boolean
     */
    public static function isDark($color) {
        try {
            $hex = static::toHex($color);
        } catch (\Throwable $e) {
            return false;
        }
        
        $c_r = hexdec( substr( $hex, 0, 2 ) );
        $c_g = hexdec( substr( $hex, 2, 2 ) );
        $c_b = hexdec( substr( $hex, 4, 2 ) );
        
        $brightness = ( ( $c_r * 299 ) + ( $c_g * 587 ) + ( $c_b * 114 ) ) / 1000;
        
        return $brightness > 155 ? false : true;
    }
    
    /**
     * Returns TRUE if the given HTML color is light and FALSE otherwise.
     *
     * @param string $color
     * @return boolean
     */
    public static function isLight($color) {
        return ! isDark($color);
    }
    
    
    /**
     * Converts HTML colors in different notations to their HEX value: returns FFFFFF for white, #FFF, FFFFFF.
     * 
     * @param string $color
     * @throws \UnexpectedValueException if the given value does not match any color
     * @return string|boolean
     */
    public static function toHex($color)
    {
        if (substr($color, 0, 1) === '#') {
            return $color;
        } elseif (ctype_xdigit($color)) {
            return $color;
        }
        
        // standard 147 HTML color names
        $colors  =  array(
            'aliceblue'=>'F0F8FF',
            'antiquewhite'=>'FAEBD7',
            'aqua'=>'00FFFF',
            'aquamarine'=>'7FFFD4',
            'azure'=>'F0FFFF',
            'beige'=>'F5F5DC',
            'bisque'=>'FFE4C4',
            'black'=>'000000',
            'blanchedalmond '=>'FFEBCD',
            'blue'=>'0000FF',
            'blueviolet'=>'8A2BE2',
            'brown'=>'A52A2A',
            'burlywood'=>'DEB887',
            'cadetblue'=>'5F9EA0',
            'chartreuse'=>'7FFF00',
            'chocolate'=>'D2691E',
            'coral'=>'FF7F50',
            'cornflowerblue'=>'6495ED',
            'cornsilk'=>'FFF8DC',
            'crimson'=>'DC143C',
            'cyan'=>'00FFFF',
            'darkblue'=>'00008B',
            'darkcyan'=>'008B8B',
            'darkgoldenrod'=>'B8860B',
            'darkgray'=>'A9A9A9',
            'darkgreen'=>'006400',
            'darkgrey'=>'A9A9A9',
            'darkkhaki'=>'BDB76B',
            'darkmagenta'=>'8B008B',
            'darkolivegreen'=>'556B2F',
            'darkorange'=>'FF8C00',
            'darkorchid'=>'9932CC',
            'darkred'=>'8B0000',
            'darksalmon'=>'E9967A',
            'darkseagreen'=>'8FBC8F',
            'darkslateblue'=>'483D8B',
            'darkslategray'=>'2F4F4F',
            'darkslategrey'=>'2F4F4F',
            'darkturquoise'=>'00CED1',
            'darkviolet'=>'9400D3',
            'deeppink'=>'FF1493',
            'deepskyblue'=>'00BFFF',
            'dimgray'=>'696969',
            'dimgrey'=>'696969',
            'dodgerblue'=>'1E90FF',
            'firebrick'=>'B22222',
            'floralwhite'=>'FFFAF0',
            'forestgreen'=>'228B22',
            'fuchsia'=>'FF00FF',
            'gainsboro'=>'DCDCDC',
            'ghostwhite'=>'F8F8FF',
            'gold'=>'FFD700',
            'goldenrod'=>'DAA520',
            'gray'=>'808080',
            'green'=>'008000',
            'greenyellow'=>'ADFF2F',
            'grey'=>'808080',
            'honeydew'=>'F0FFF0',
            'hotpink'=>'FF69B4',
            'indianred'=>'CD5C5C',
            'indigo'=>'4B0082',
            'ivory'=>'FFFFF0',
            'khaki'=>'F0E68C',
            'lavender'=>'E6E6FA',
            'lavenderblush'=>'FFF0F5',
            'lawngreen'=>'7CFC00',
            'lemonchiffon'=>'FFFACD',
            'lightblue'=>'ADD8E6',
            'lightcoral'=>'F08080',
            'lightcyan'=>'E0FFFF',
            'lightgoldenrodyellow'=>'FAFAD2',
            'lightgray'=>'D3D3D3',
            'lightgreen'=>'90EE90',
            'lightgrey'=>'D3D3D3',
            'lightpink'=>'FFB6C1',
            'lightsalmon'=>'FFA07A',
            'lightseagreen'=>'20B2AA',
            'lightskyblue'=>'87CEFA',
            'lightslategray'=>'778899',
            'lightslategrey'=>'778899',
            'lightsteelblue'=>'B0C4DE',
            'lightyellow'=>'FFFFE0',
            'lime'=>'00FF00',
            'limegreen'=>'32CD32',
            'linen'=>'FAF0E6',
            'magenta'=>'FF00FF',
            'maroon'=>'800000',
            'mediumaquamarine'=>'66CDAA',
            'mediumblue'=>'0000CD',
            'mediumorchid'=>'BA55D3',
            'mediumpurple'=>'9370D0',
            'mediumseagreen'=>'3CB371',
            'mediumslateblue'=>'7B68EE',
            'mediumspringgreen'=>'00FA9A',
            'mediumturquoise'=>'48D1CC',
            'mediumvioletred'=>'C71585',
            'midnightblue'=>'191970',
            'mintcream'=>'F5FFFA',
            'mistyrose'=>'FFE4E1',
            'moccasin'=>'FFE4B5',
            'navajowhite'=>'FFDEAD',
            'navy'=>'000080',
            'oldlace'=>'FDF5E6',
            'olive'=>'808000',
            'olivedrab'=>'6B8E23',
            'orange'=>'FFA500',
            'orangered'=>'FF4500',
            'orchid'=>'DA70D6',
            'palegoldenrod'=>'EEE8AA',
            'palegreen'=>'98FB98',
            'paleturquoise'=>'AFEEEE',
            'palevioletred'=>'DB7093',
            'papayawhip'=>'FFEFD5',
            'peachpuff'=>'FFDAB9',
            'peru'=>'CD853F',
            'pink'=>'FFC0CB',
            'plum'=>'DDA0DD',
            'powderblue'=>'B0E0E6',
            'purple'=>'800080',
            'red'=>'FF0000',
            'rosybrown'=>'BC8F8F',
            'royalblue'=>'4169E1',
            'saddlebrown'=>'8B4513',
            'salmon'=>'FA8072',
            'sandybrown'=>'F4A460',
            'seagreen'=>'2E8B57',
            'seashell'=>'FFF5EE',
            'sienna'=>'A0522D',
            'silver'=>'C0C0C0',
            'skyblue'=>'87CEEB',
            'slateblue'=>'6A5ACD',
            'slategray'=>'708090',
            'slategrey'=>'708090',
            'snow'=>'FFFAFA',
            'springgreen'=>'00FF7F',
            'steelblue'=>'4682B4',
            'tan'=>'D2B48C',
            'teal'=>'008080',
            'thistle'=>'D8BFD8',
            'tomato'=>'FF6347',
            'turquoise'=>'40E0D0',
            'violet'=>'EE82EE',
            'wheat'=>'F5DEB3',
            'white'=>'FFFFFF',
            'whitesmoke'=>'F5F5F5',
            'yellow'=>'FFFF00',
            'yellowgreen'=>'9ACD32');
        
        $color = strtolower($color);
        if ($hex = $colors[$color]) {
            return $hex;
        } else {
            throw new \UnexpectedValueException('Cannot convert ' . $color . ' to HEX color code!');
        }
    }
}
