<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;

/**
 * Encodes or decodes Base64 strings 
 * 
 * Examples:
 * 
 * - `=Base64('my_string')` - encodes "my_string" as Base64 yielding "bXlfc3RyaW5n"
 * - `=Base64('bXlfc3RyaW5n', true)` - decodes "bXlfc3RyaW5n" back to "my_string"
 *
 * @author Andrej Kabachnik
 *        
 */
class Base64 extends Formula
{

    /**
     * 
     * @param string $string
     * @param bool $encode
     * @return string|NULL
     */
    function run($string = null, bool $decode = false)
    {
        if ($string === null || $string === '') {
            return $string;
        }
        return $decode !== true ? base64_encode($string) : base64_decode($string);
    }
}