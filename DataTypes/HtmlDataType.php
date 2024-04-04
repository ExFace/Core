<?php
namespace exface\Core\DataTypes;

use Gajus\Dindent\Indenter;

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
    
    /**
     * Returns the prettified version of the given HTML.
     * 
     * NOTE: this method does not modify the HTML in contrast DOMDocument::$formatOutput and other beautificators.
     * 
     * @param string $html
     * @return string
     */
    public static function prettify(string $html) : string
    {
        return (new Indenter())->indent($html);
    }
}