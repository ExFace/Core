<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\BooleanDataType;

/**
 * Returns the specified number of characters from the right of the given string
 *
 * @author Andrej Kabachnik
 *        
 */
class Right extends Formula
{

    /**
     * 
     * @param string $text
     * @param number $numChars
     * @param bool $dots
     * @return string|NULL
     */
    function run($text = null, $numChars = 1, bool $dots = true)
    {
        if ($text === null || $text === '') {
            return $text;
        }
        
        $length = mb_strlen($text);
        if ($length <= $numChars) {
            return $text;
        }
        
        // Make sure, the dots (ellipsis) fit into to desired length
        if ($dots && $numChars > 4) {
            $numChars = $numChars - 3;
        } else {
            $dots = false;
        }
        
        $truncated = mb_substr($text, $length - $numChars);
        
        if ($truncated !== $text && $dots) {
            $truncated = '...' . $text;
        }
        
        return $truncated;
    }
}