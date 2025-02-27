<?php
namespace exface\Core\DataTypes;

use DOMDocument;
use exface\Core\Exceptions\DataTypes\HtmlValidationError;
use Gajus\Dindent\Indenter;

class HtmlDataType extends TextDataType
{
    const URL_TYPE_RELATIVE = 'relative';
    CONST URL_TYPE_ALL = 'all';
    
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

    /**
     * Parses the given HTML code and throws detailed errors and warnings if any inconsistencies are found.
     * 
     * @param string $html
     * @throws HtmlValidationError
     * @return string
     */
    public static function validateHtml(string $html) : string
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED + LIBXML_HTML_NODEFDTD);
        $errors = libxml_get_errors();

        if(count($errors) > 0) {
            throw new HtmlValidationError("HTML validation error", null, null, $html, $errors);
        }

        return $html;
    }

    public static function stripLinks(string $html, string $linkType = self::URL_TYPE_ALL) : string
    {
        $result = '';
        switch ($linkType) {
            case self::URL_TYPE_RELATIVE:
                $result = preg_replace_callback('/<a\s+[^>]*href=["\'](?!https?:\/\/)([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function ($matches) {
                    return $matches[2]; // Return only the inner text, removing the anchor tag
                }, $html);
                break;
            case self::URL_TYPE_ALL:
                $result = preg_replace("(</?a[^>]*\>)i", "", $html);
                break;
        }
        return $result;
    }
}