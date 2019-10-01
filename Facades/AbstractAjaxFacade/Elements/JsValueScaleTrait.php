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
            $scaleValsJs .= '[' . $val . ',  "' . $color . '"],';
        }
        $scaleValsJs = rtrim($scaleValsJs, ",");
        
        return <<<JS

function() {
    var val = {$valueJs};
    
    if (val === undefined || val === '' || val === null) return '';

    var scale = [ {$scaleValsJs} ];
    var numVal = parseFloat(val);

    var sv = [];
    for (var i in scale) {
        sv = scale[i];
        if (numVal <= sv[0]) {
            return sv[1];
        }
    }
    return sv[1];
}()

JS;
    }
    
    /**
     *
     * @param string $valueJs
     * @param array $scale
     * @return string
     */
    protected function buildJsScaleResolverForValues(string $valueJs, array $scale) : string
    {
        $scaleValsJs = json_encode($scale);
        
        return <<<JS
        
function() {
    var val = {$valueJs};
    
    if (val === undefined || val === '' || val === null) return '';
    
    val = val.toString().toLowerCase();
    var scale = {$scaleValsJs};
    
    for (var i in scale) {
        if (val == i.toLowerCase()) {
            return scale[i];
        }
    }
    return scale[''] || '';
}()

JS;
    }
}
?>