<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

/**
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JsValueScaleTrait
{   
    
    /**
     * 
     * @param string $valueJs
     * @param array $scale
     * @param bool $isRangeScale
     * @return string
     */
    protected function buildJsScaleResolver(string $valueJs, array $scale, bool $isRangeScale) : string
    {
        return $isRangeScale ? $this->buildJsScaleResolverForNumbers($valueJs, $scale) : $this->buildJsScaleResolverForValues($valueJs, $scale);
    }
    
    /**
     * 
     * @param string $valueJs
     * @param array $scale
     * @return string
     */
    protected function buildJsScaleResolverForNumbers(string $valueJs, array $scale) : string
    {
        // The scale is presented as an array of arrays in JS because an object does not
        // retain the order of keys, which is crucial in this case.
        $scaleValsJs = '';
        foreach ($scale as $val => $color) {
            $scaleValsJs .= '[' . (is_numeric($val) || $val === null ? $val : "'$val'")  . ',  "' . $color . '"],';
        }
        $scaleValsJs = rtrim($scaleValsJs, ",");
        
        return <<<JS

(function(mVal) {
    var aScale = [ {$scaleValsJs} ];
    var numVal;

    if (mVal === undefined || mVal === '' || mVal === null) return '';

    if (isNaN(mVal)) {
        var numVal = parseFloat(mVal.replace(' ', ''));
    } else {
        numVal = mVal;
    }

    var sv = [];
    for (var i in aScale) {
        sv = aScale[i];
        if (numVal <= sv[0]) {
            return sv[1];
        }
    }
    return sv[1];
})({$valueJs})

JS;
    }
    
    /**
     *
     * @param string $valueJs
     * @param array $scale
     * @param string[] $resultMap
     * @return string
     */
    protected function buildJsScaleResolverForValues(string $valueJs, array $scale, array $resultMap = []) : string
    {
        $scaleValsJs = json_encode($scale);
        
        return <<<JS
        
(function(mVal) {
    var oScale = {$scaleValsJs};

    if (mVal === undefined || mVal === '' || mVal === null) return '';
    
    mVal = mVal.toString().toLowerCase();
    for (var i in oScale) {
        if (mVal == i.toLowerCase()) {
            return oScale[i];
        }
    }
    return oScale[''] || '';
})({$valueJs})

JS;
    }
}
?>