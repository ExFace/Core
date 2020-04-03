<?php
namespace exface\Core\Formulas;

/**
 * Truncates a value leaving the specified number of characters from the left
 *
 * @author Andrej Kabachnik
 *        
 */
class Left extends \exface\Core\CommonLogic\Model\Formula
{

    function run($text, $numChars = 1)
    {
        return mb_substr($text, 0, $numChars);
    }
}