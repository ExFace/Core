<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\BooleanDataType;

/**
 * Truncates a value leaving the specified number of characters from the left
 *
 * @author Andrej Kabachnik
 *        
 */
class Left extends \exface\Core\CommonLogic\Model\Formula
{

    /**
     * 
     * @param string $text
     * @param number $numChars
     * @param boolean $stickToWords
     * @param bool $dots
     * @return string|NULL
     */
    function run($text, $numChars = 1, $stickToWords = false, bool $dots = true)
    {
        if ($text === null || $text === '') {
            return $text;
        }
        
        if (mb_strlen($text) <= $numChars) {
            return $text;
        }
        
        // Make sure, the dots (ellipsis) fit into to desired length
        if ($dots && $numChars > 4) {
            $numChars = $numChars - 3;
        } else {
            $dots = false;
        }
        
        $truncated = $text;
        if ($stickToWords === false || BooleanDataType::cast($stickToWords) === false) {
            $truncated = mb_substr($truncated, 0, $numChars);
        } else {
            $truncated = wordwrap($truncated, $numChars);
            $truncated = mb_substr($truncated, 0, mb_strpos($truncated, "\n"));
        }
        if ($truncated !== $text && $dots) {
            $truncated .= '...';
        }
        
        return $truncated;
    }
}