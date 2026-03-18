<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\StringDataType;

/**
 * Returns the part of the given text (haystack) following the first occurrence of the needle (delimiter).
 * 
 * Examples:
 * 
 * - `=SubstringAfter('123_Text', '_') => 'Text'`
 * - `=SubstringAfter('32450196,06', '32') => '450196,06'`
 *
 * @author Andrej Kabachnik
 *        
 */
class SubstringAfter extends Formula
{

    /**
     * 
     * @param string $text
     * @param string $delimiter
     * @return string|NULL
     */
    public function run($text = null, string $delimiter = null)
    {
        if($text === '' || $text === null || $delimiter === '' || $delimiter === null) {
            return $text;
        }
        return StringDataType::substringAfter($text, $delimiter, '');
    }
}