<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\BinaryDataType;

/**
 * Encodes or decodes Base64 strings 
 * 
 * Examples:
 * 
 * - `=Base64('example')` - encodes "example" as Base64 yielding "ZXhhbXBsZQ=="
 * - `=Base64('ZXhhbXBsZQ==', true)` - decodes "ZXhhbXBsZQ==" back to "example"
 * - `=Base64('example', false, true)` - encodes "example" to a URL-compatible Base64 string "ZXhhbXBsZQ"
 * - `=Base64('ZXhhbXBsZQ', true, true)` - decodes "ZXhhbXBsZQ" back to "example"
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
    function run($string = null, bool $decode = false, bool $urlCompatible = false)
    {
        if ($string === null || $string === '') {
            return $string;
        }
        if ($urlCompatible === true) {
            $result = $decode !== true ? BinaryDataType::convertTextToBase64URL($string) : BinaryDataType::convertBase64URLToText($string);
        } else {
            $result = $decode !== true ? BinaryDataType::convertTextToBase64($string) : BinaryDataType::convertBase64ToText($string);
        }
        return $result;
    }
}