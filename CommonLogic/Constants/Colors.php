<?php

namespace exface\Core\CommonLogic\Constants;

/**
 * Color constants for basic colors supported by all templates.
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
    // Default color to make the template pick a color
    const DEFAULT = '';
    // No-color-color to tell a template that something is transparent
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
}
