<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\BooleanDataType;

/**
 * Cuts out a string from the given text: takes `length` characters starting at a given `start` position 
 *
 * @author Andrej Kabachnik
 *        
 */
class Substring extends Formula
{

    /**
     *
     * @param null $text
     * @param int $start
     * @param int|null $length
     * @return string|NULL
     */
    function run($text = null, int $start = 1, ?int $length = null)
    {
        if ($text === null || $text === '') {
            return $text;
        }
        
        return mb_substr($text, $start-1, $length);        
    }
}