<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Widgets\Input;
use exface\Core\DataTypes\NumberDataType;

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
        $validatorJs = $this->buildJsValidatorCheckRequired('val', 'return false;')
        . $this->buildJsValidatorCheckDataType('val', 'return false;', $this->getWidget()->getValueDataType());
        
        $valJs = $valJs ?? $this->buildJsValueGetter();
        if ($validatorJs !== '') {
            return "(function(){ 
                        var val = {$valJs}; 
                        $validatorJs;
                        return true; 
                    })()";
        } else {
            return 'true';
        }
    }
    
    /**
     * Returns a JS snippet, that performs $onFailJs if the widget is required and has not value.
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
     * Returns a JS snippet, that performs $onFailJs if the current value does not match data type contraints.
     * 
     * @param string $valueJs
     * @param string $onFailJs
     * @param DataTypeInterface $type
     * @return string
     */
    protected function buildJsValidatorCheckDataType(string $valueJs, string $onFailJs, DataTypeInterface $type) : string
    {
        switch (true) {
            case $type instanceof StringDataType:
                // Do not validate max-length if multiple values are allowed
                // TODO split values here instead?
                if (($this->getWidget() instanceof Input) && $this->getWidget()->getMultipleValuesAllowed() === true) {
                    $formatter = $this->getFacade()->getDataTypeFormatter($type->copy()->setLengthMax(null));
                    break;
                }
            default:
                $formatter = $this->getFacade()->getDataTypeFormatter($type);
                break;
        }
        $typeValidator = $formatter->buildJsValidator($valueJs);
        return $typeValidator ? "if($typeValidator === false) {$onFailJs};" : '';
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
            $text = ($text ? rtrim($text, ".!") . '. ' : $text) . $translator->translate('WIDGET.INPUT.VALIDATION_REQUIRED');
        }
        
        return $text ? $text : $translator->translate('WIDGET.INPUT.VALIDATION_UNKNOWN_ERROR');
    }
}
?>
