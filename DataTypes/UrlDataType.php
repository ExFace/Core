<?php
namespace exface\Core\DataTypes;

class UrlDataType extends StringDataType
{
    /**
     * Advanced version of urlencode() that prevents parameter names to be altered by PHP.
     * 
     * @link http://php.net/variables.external#language.variables.external.dot-in-names
     * 
     * @param string $string
     * @return string
     */
    public static function urlEncode(string $string) : string
    {
        return str_replace('.', '%2E', urlencode($string));
    }
}
?>