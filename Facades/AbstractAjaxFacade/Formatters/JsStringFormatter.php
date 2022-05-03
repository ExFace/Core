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
            $js .= "if($nullCheckJs && $jsValue.toString().length < {$type->getLengthMin()}) { return false; } \n";
        }
        
        if ($type->getLengthMax() > 0) {
            $js .= "if($nullCheckJs && $jsValue.toString().length > {$type->getLengthMax()}) { return false; } \n";
        }
        
        if ($type->getValidatorRegex() !== null) {
            $js .= "if($nullCheckJs && {$type->getValidatorRegex()}.test({$jsValue}) === false) { return false; } \n";
        }
        
        return $js;
    }   
}
