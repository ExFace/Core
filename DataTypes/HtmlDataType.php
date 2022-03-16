<?php
namespace exface\Core\DataTypes;

class HtmlDataType extends TextDataType
{
    /**
     * Returns TRUE if the given string contains HTML tags and FALSE otherwise.
     * 
     * @param string $string
     * @return bool
     */
    public static function isValueHtml(string $string) : bool
    {
        return $string != strip_tags($string);
    }
}