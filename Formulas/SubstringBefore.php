<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\StringDataType;

/**
 * Returns the part of a text up to the given delimiter
 *
 * @author Andrej Kabachnik
 *        
 */
class SubstringBefore extends Formula
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
        return StringDataType::substringBefore($text, $delimiter, $text);
    }
}