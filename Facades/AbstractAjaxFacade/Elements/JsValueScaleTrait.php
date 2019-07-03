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
    
    if (val === undefined || val === '') return '';

    var scale = [ {$scaleValsJs} ];
    var numVal = parseFloat(val);

    var sv = [];
    for (var i in scale) {
        sv = colorMap[i];
        if (numVal <= sv[0]) {
            return sv[1];
        }
    }
}()

JS;
    }
}
?>