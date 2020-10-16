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

    function run($text, $numChars = 1, $stickToWords = false)
    {
        if ($stickToWords === false || BooleanDataType::cast($stickToWords) === false) {
            return mb_substr($text, 0, $numChars);
        } else {
            if (strlen($text) > $numChars) {
                $text = wordwrap($text, $numChars);
                $text = mb_substr($text, 0, mb_strpos($text, "\n"));
            }
            return $text;
        }
    }
}