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
        $js = '';
        $nullStr = "'" . EXF_LOGICAL_NULL . "'";
        $nullCheckJs = "$jsValue.toString() !== '' && $jsValue.toString() !== $nullStr";

        // Validate string min legnth
        if ($type->getLengthMin() > 0) {
            $js .= "($nullCheckJs && $jsValue.toString().length < {$type->getLengthMin()} ? false : true) \n";
        }
        
        if ($type->getLengthMax() > 0) {
            $js .= "($nullCheckJs && $jsValue.toString().length > {$type->getLengthMax()} ? false : true) \n";
        }
        
        if ($type->getValidatorRegex() !== null) {
            $js .= "($nullCheckJs && {$type->getValidatorRegex()}.test({$jsValue}) === false ? false : true ) \n";
        }
        
        return $js;
    }   
}
