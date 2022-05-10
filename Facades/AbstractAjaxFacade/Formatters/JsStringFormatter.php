<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

/**
 * The string formatter does not really format, but it takes care of validation
 * 
 * @author Andrej Kabachnik
 *
 */
class JsStringFormatter extends JsTransparentFormatter
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Formatters\JsTransparentFormatter::buildJsValidator()
     */
    public function buildJsValidator(string $jsValue) : string
    {
        $type = $this->getDataType();
        
        $checksOk = [];
        if ($type->getLengthMin() > 0) {
            $checksOk[] = "mVal.toString().length >= {$type->getLengthMin()} \n";
        }
        
        if ($type->getLengthMax() > 0) {
            $checksOk[] = "mVal.toString().length <= {$type->getLengthMax()} \n";
        }
        
        if ($type->getValidatorRegex() !== null) {
            $checksOk[] = "{$type->getValidatorRegex()}.test({$jsValue}) !== false \n";
        }
        $checksOkJs = ! empty($checksOk) ? implode(' && ', $checksOk) : 'true';
        
        $nullStr = '" . EXF_LOGICAL_NULL . "';
        return <<<JS
function(mVal) {
                var bEmpty = (mVal === null || mVal === undefined || mVal.toString() === '' || mVal.toString() === $nullStr);
                return (bEmpty || ($checksOkJs));
            }($jsValue)
JS;
    }   
}
