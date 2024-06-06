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
    // defines the whitelist of elements and attributes allowed.
    private static $whitelist = array(
        'a'=>array('class', 'clip-path', 'clip-rule', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'id', 'mask', 'opacity', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform', /*'href', 'xlink:href',*/ 'xlink:title'),
        'circle'=>array('class', 'clip-path', 'clip-rule', 'cx', 'cy', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'id', 'mask', 'opacity', 'r', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform'),
        'clipPath'=>array('class', 'clipPathUnits', 'id'),
        'defs'=>array(),
        'style' =>array('type'),
        'desc'=>array(),
        'ellipse'=>array('class', 'clip-path', 'clip-rule', 'cx', 'cy', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'id', 'mask', 'opacity', 'requiredFeatures', 'rx', 'ry', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform'),
        'feGaussianBlur'=>array('class', 'color-interpolation-filters', 'id', 'requiredFeatures', 'stdDeviation'),
        'filter'=>array('class', 'color-interpolation-filters', 'filterRes', 'filterUnits', 'height', 'id', 'primitiveUnits', 'requiredFeatures', 'width', 'x', /*'xlink:href',*/ 'y'),
        'foreignObject'=>array('class', 'font-size', 'height', 'id', 'opacity', 'requiredFeatures', 'style', 'transform', 'width', 'x', 'y'),
        'g'=>array('class', 'clip-path', 'clip-rule', 'id', 'display', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'mask', 'opacity', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform', 'font-family', 'font-size', 'font-style', 'font-weight', 'text-anchor'),
        'image'=>array('class', 'clip-path', 'clip-rule', 'filter', 'height', 'id', 'mask', 'opacity', 'requiredFeatures', 'style', 'systemLanguage', 'transform', 'width', 'x', /*'xlink:href',*/ 'xlink:title', 'y'),
        'line'=>array('class', 'clip-path', 'clip-rule', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'id', 'marker-end', 'marker-mid', 'marker-start', 'mask', 'opacity', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform', 'x1', 'x2', 'y1', 'y2'),
        'linearGradient'=>array('class', 'id', 'gradientTransform', 'gradientUnits', 'requiredFeatures', 'spreadMethod', 'systemLanguage', 'x1', 'x2', /*'xlink:href',*/ 'y1', 'y2'),
        'marker'=>array('id', 'class', 'markerHeight', 'markerUnits', 'markerWidth', 'orient', 'preserveAspectRatio', 'refX', 'refY', 'systemLanguage', 'viewBox'),
        'mask'=>array('class', 'height', 'id', 'maskContentUnits', 'maskUnits', 'width', 'x', 'y'),
        'metadata'=>array('class', 'id'),
        'path'=>array('class', 'clip-path', 'clip-rule', 'd', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'id', 'marker-end', 'marker-mid', 'marker-start', 'mask', 'opacity', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform'),
        'pattern'=>array('class', 'height', 'id', 'patternContentUnits', 'patternTransform', 'patternUnits', 'requiredFeatures', 'style', 'systemLanguage', 'viewBox', 'width', 'x', /*'xlink:href',*/ 'y'),
        'polygon'=>array('class', 'clip-path', 'clip-rule', 'id', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'id', 'class', 'marker-end', 'marker-mid', 'marker-start', 'mask', 'opacity', 'points', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform'),
        'polyline'=>array('class', 'clip-path', 'clip-rule', 'id', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'marker-end', 'marker-mid', 'marker-start', 'mask', 'opacity', 'points', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform'),
        'radialGradient'=>array('class', 'cx', 'cy', 'fx', 'fy', 'gradientTransform', 'gradientUnits', 'id', 'r', 'requiredFeatures', 'spreadMethod', 'systemLanguage'/*, 'xlink:href'*/),
        'rect'=>array('class', 'clip-path', 'clip-rule', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'height', 'id', 'mask', 'opacity', 'requiredFeatures', 'rx', 'ry', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform', 'width', 'x', 'y'),
        'stop'=>array('class', 'id', 'offset', 'requiredFeatures', 'stop-color', 'stop-opacity', 'style', 'systemLanguage'),
        'svg'=>array('class', 'clip-path', 'clip-rule', 'filter', 'id', 'height', 'mask', 'preserveAspectRatio', 'requiredFeatures', 'style', 'systemLanguage', 'viewBox', 'width', 'x', 'xmlns', 'xmlns:se', 'xmlns:xlink', 'y'),
        'switch'=>array('class', 'id', 'requiredFeatures', 'systemLanguage'),
        'symbol'=>array('class', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'font-family', 'font-size', 'font-style', 'font-weight', 'id', 'opacity', 'preserveAspectRatio', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'transform', 'viewBox'),
        'text'=>array('class', 'clip-path', 'clip-rule', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'font-family', 'font-size', 'font-style', 'font-weight', 'id', 'mask', 'opacity', 'requiredFeatures', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'text-anchor', 'transform', 'x', 'xml:space', 'y'),
        'textPath'=>array('class', 'id', 'method', 'requiredFeatures', 'spacing', 'startOffset', 'style', 'systemLanguage', 'transform'/*, 'xlink:href'*/),
        'title'=>array(),
        'tspan'=>array('class', 'clip-path', 'clip-rule', 'dx', 'dy', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'font-family', 'font-size', 'font-style', 'font-weight', 'id', 'mask', 'opacity', 'requiredFeatures', 'rotate', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'systemLanguage', 'text-anchor', 'textLength', 'transform', 'x', 'xml:space', 'y'),
        'use'=>array('class', 'clip-path', 'clip-rule', 'fill', 'fill-opacity', 'fill-rule', 'filter', 'height', 'id', 'mask', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'style', 'transform', 'width', 'x', /*'xlink:href', */'y'),
    );
    
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
        
        $result = $xmlDoc->saveHTML();
        if ($result === false) {
            throw new DataTypeCastingError('Cannot parse SVG!');
        }
        return $result;       
    }
    
    /**
     * 
     * @param \DOMDocument $xml
     * @return bool
     */
    protected static function sanitizeSvg(\DOMDocument $xml) : bool
    {
        // all elements in xml doc
        $allElements = $xml->getElementsByTagName("*");
        
        // loop through all elements
        for($i = 0; $i < $allElements->length; $i++)
        {
            $currentNode = $allElements->item($i);
            
            
            // array of allowed attributes in specific element
            $whitelist_attr_arr = self::$whitelist[$currentNode->tagName];
            
            // does element exist in whitelist?
            if(isset($whitelist_attr_arr)) {
                
                for($x = 0; $x < $currentNode->attributes->length; $x++) {
                    
                    // get attributes name
                    $attrName = $currentNode->attributes->item($x)->name;
                    
                    // check if attribute isn't in whiltelist
                    if(!in_array($attrName,$whitelist_attr_arr)) {
                        $currentNode->removeAttribute($attrName);
                        $x--;
                    }
                }
            }
            
            // else remove element
            else {
                
                $currentNode->parentNode->removeChild($currentNode);
                $i--;
                
            }
        }
        
        return $xml;
    }
}