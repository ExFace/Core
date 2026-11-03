<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

/**
 * Data type for SVG images including tools to validate and sanitize SVGs
 * 
 * @author Andrej Kabachnik
 *
 */
class SvgDataType extends AbstractDataType
{
    /**
     * 
     * @param string $xml
     * @return \DOMDocument
     */
    protected static function parseToXmlDoc(string $xml) {
        $xmlDoc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $xmlDoc->preserveWhiteSpace = false;
        $xmlDoc->loadXML($xml);
        return $xmlDoc;
    }
    
    /**
     * 
     * @param string|null $string
     */
    public static function cast($string)
    {
        if ($string === null || $string === '') {
            return $string;
        }
        
        try {
            $xmlDoc = static::parseToXmlDoc($string);
        } catch (\Throwable $e) {
            throw new DataTypeCastingError('Invalid SVG! ' . $e->getMessage(), null, $e);
        }
        
        if ($xmlDoc->childNodes->length === 0) {
            throw new DataTypeCastingError('Cannot parse SVG!');
        }
        
        $result = $xmlDoc->saveXML();
        if ($result === false) {
            throw new DataTypeCastingError('Cannot parse SVG!');
        }
        return $result;       
    }
}