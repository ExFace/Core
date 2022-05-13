<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Widgets\Input;

/**
 * This trait contains a generic buildJsValidator() method and some usefull helpers for 
 * facade elements of input widgets. 
 * 
 * @method iTakeInput getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryInputValidationTrait {
    
    /**
     * Returns TRUE if the widget needs validation at all - i.e. if it will have affect on
     * the data produced by it's container.
     * 
     * @return bool
     */
    protected function isValidationRequired() : bool
    {
        $widget = $this->getWidget();
        return ! ($widget->isHidden() || $widget->isReadonly() || $widget->isDisabled() || $widget->isDisplayOnly());
    }
    
    /**
     * Returns an inline JS expression, that evaluates to FALSE if validation fails and TRUE if it passes.
     * 
     * NOTE: The parameter $valJs is required for in-table inputs, where the validation must
     * be integrated into the table code!
     * 
     * If no validation required, override this method with `return 'true'` - see typical InputCheckBox
     * implementations.
     * 
     * {@inheritdoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValidator()
     */
    public function buildJsValidator(string $valJs = null)
    {
        $constraintsJs = $this->buildJsValidatorCheckRequired('val', 'bConstraintsOK = false;')
        . $this->buildJsValidatorConstraints('val', 'bConstraintsOK = false;', $this->getWidget()->getValueDataType());
        
        $valJs = $valJs ?? $this->buildJsValueGetter();
        if ($constraintsJs !== '') {
            return "(function(){ 
                        var val = {$valJs}; 
                        var bConstraintsOK = true;
                        $constraintsJs;
                        return bConstraintsOK; 
                    })()";
        } else {
            return 'true';
        }
    }
    
    /**
     * Returns JS code, that performs $onFailJs if the widget is required and has not value.
     * 
     * @param string $valueJs
     * @param string $onFailJs
     * 
     * @return string
     */
    protected function buildJsValidatorCheckRequired(string $valueJs, string $onFailJs) : string
    {
        if ($this->getWidget()->isRequired() === true) {
            return "if ($valueJs == null || $valueJs === '') { $onFailJs }";
        }
        return '';
    }
    
    /**
     * Returns JS code, that performs $onFailJs if the current value does not match any of the widgets contraints.
     * 
     * In most cases, the result will be a series of IFs, each calling $onFailJs if the constraint fails.
     * To introduce more constraints for specific facade element implementations, just append more IFs.
     * 
     * By default this trait will validate the data type by letting the JS data type formatter render a
     * validator script.
     * 
     * @param string $valueJs
     * @param string $onFailJs
     * @param DataTypeInterface $type
     * 
     * @return string
     */
    protected function buildJsValidatorConstraints(string $valueJs, string $onFailJs, DataTypeInterface $type) : string
    {
        $widget = $this->getWidget();
        $formatter = $this->getFacade()->getDataTypeFormatter($type);
        
        // If the input allows multiple values as a delimited list, apply the validation to each
        // part of the list - in particular to check string length for each value individually
        if (($type instanceof StringDataType) && ($widget instanceof Input) && $widget->getMultipleValuesAllowed() === true) {
            $partValidator = $formatter->buildJsValidator('part');
            return <<<JS

                    if ($valueJs !== undefined && $valueJs !== null && Array.isArray($valueJs) === false) {
                        $valueJs.toString().split("{$widget->getMultipleValuesDelimiter()}").forEach(function(part){
                            if ($partValidator !== true) {
                                {$onFailJs}
                            }
                        });
                    }
JS;
        }
        
        $typeValidator = $formatter->buildJsValidator($valueJs);
        return $typeValidator ? "if($typeValidator !== true) {$onFailJs};" : '';
    }
    
    /**
     * Returns the hint to tell the user why validation failed.
     * 
     * If the data data type of the value of the widget has a custom validation error text,
     * that text will be used as-is. Otherwise this method will generate a text automatically
     * based on the properties of the widget an the constraints of the value data type. 
     * 
     * @return string
     */
    public function getValidationErrorText() : string
    {
        $widget = $this->getWidget();
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $text = '';
        
        if ($msg = $widget->getValueDataType()->getValidationErrorMessage()) {
            $text = $msg->getTitle();
        }
        
        if ($widget->isRequired()) {
            $text = ($text ? rtrim(trim($text), ".!") . '. ' : $text) . $translator->translate('WIDGET.INPUT.VALIDATION_REQUIRED');
        }
        
        return $text ? $text : $translator->translate('WIDGET.INPUT.VALIDATION_UNKNOWN_ERROR');
    }
}
?>
