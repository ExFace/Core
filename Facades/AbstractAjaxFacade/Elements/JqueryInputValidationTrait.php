<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\StringDataType;

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
     * If no validation required, override this method with `return 'true'` - see typical InputCheckBox
     * implementations.
     * 
     * {@inheritdoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValidator()
     */
    public function buildJsValidator()
    {
        $validatorJs = $this->buildJsValidatorCheckRequired('val', 'return false;')
        . $this->buildJsValidatorCheckDataType('val', 'return false;', $this->getWidget()->getValueDataType());
        
        if ($validatorJs !== '') {
            return "(function(){ 
                        var val = {$this->buildJsValueGetter()}; 
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
            return "if ($valueJs === '') { $onFailJs }";
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
        $js = '';
        switch (true) {
            case $type instanceof StringDataType:
                if ($type->getLengthMin() > 0) {
                    $js .= "if($valueJs.toString().length < {$type->getLengthMin()}) { $onFailJs } \n";
                }
                if ($type->getLengthMax() > 0) {
                    $js .= "if($valueJs.toString().length > {$type->getLengthMax()}) { $onFailJs } \n";
                }
                
                break;
        }
        return $js;
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
    protected function getValidationErrorText() : string
    {
        $widget = $this->getWidget();
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $text = '';
        
        $type = $widget->getValueDataType();
        if ($type->getValidationErrorCode()) {
            // TODO get message from error message model
        }
        if ($type->getValidationErrorText()) {
            return $type->getValidationErrorText();
        }
        switch (true) {
            case $type instanceof StringDataType:
                $and = $translator->translate('WIDGET.INPUT.VALIDATION_AND');
                if ($type->getLengthMin() > 0) {
                    $lengthCond = ' ≥ ' . $type->getLengthMin();
                }
                if ($type->getLengthMax() > 0) {
                    $lengthCond .= ($lengthCond ? ' ' . $and . ' ' : '') . ' ≤ ' . $type->getLengthMax();
                }
                if ($lengthCond) {
                    $text .= $translator->translate('WIDGET.INPUT.VALIDATION_LENGTH_CONDITION', ['%condition%' => $lengthCond]);
                }
                break;
        }
        
        if ($text !== '') {
            $text = $translator->translate('WIDGET.INPUT.VALIDATION_MUST') . ' ' . $text;
        }
        
        if ($widget->isRequired()) {
            $text .= ($text ? '. ' : '') . $translator->translate('WIDGET.INPUT.VALIDATION_REQUIRED');
        }
        return $text;
    }
}
?>
