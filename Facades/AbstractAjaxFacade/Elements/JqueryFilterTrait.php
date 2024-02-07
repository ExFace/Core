<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 *
 * @method \exface\Core\Widgets\Filter getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryFilterTrait {

    public function buildJsConditionGetter($valueJs = null)
    {
        $widget = $this->getWidget();
        if ($widget->hasCustomConditionGroup() === true) {
            return '';
        }
        if ($widget->isDisplayOnly() === true) {
            return '';
        }
        $value = is_null($valueJs) ? $this->buildJsValueGetter() : $valueJs;
        if ($widget->getAttributeAlias() === '' || $widget->getAttributeAlias() === null) {
            throw new WidgetConfigurationError($widget, 'Invalid filter configuration for filter "' . $widget->getCaption() . '": missing expression (e.g. attribute_alias)!');
        }
        return '{expression: "' . $widget->getAttributeAlias() . '", comparator: ' . $this->buildJsComparatorGetter() . ', value: ' . $value . ', object_alias: "' . $widget->getMetaObject()->getAliasWithNamespace() . '"}';
    }
    
    public function buildJsCustomConditionGroup($valueJs = null) : string
    {
        $widget = $this->getWidget();
        if ($widget->hasCustomConditionGroup() === false) {
            return '';
        }
        
        $jsonWithValuePlaceholder = $widget->getCustomConditionGroup()->exportUxonObject()->toJson(false);
        return str_replace('"[#value#]"', $valueJs ?? $this->buildJsValueGetter(), $jsonWithValuePlaceholder);
    }
    
    public function buildJsComparatorGetter()
    {
        return '"' . $this->getWidget()->getComparator() . '"';
    }

    public function buildJsValueGetter()
    {
        return $this->getInputElement()->buildJsValueGetter();
    }

    public function buildJsValueGetterMethod()
    {
        return $this->getInputElement()->buildJsValueGetterMethod();
    }

    public function buildJsValueSetter($value)
    {
        return $this->getInputElement()->buildJsValueSetter($value);
    }

    public function buildJsValueSetterMethod($value)
    {
        return $this->getInputElement()->buildJsValueSetterMethod($value);
    }

    public function buildJsInitOptions()
    {
        return $this->getInputElement()->buildJsInitOptions();
    }

    public function getInputElement()
    {
        return $this->getFacade()->getElement($this->getWidget()->getInputWidget());
    }
    
    public function addOnChangeScript($string)
    {
        $this->getInputElement()->addOnChangeScript($string);
        return $this;
    }

    /**
     * Magic method to forward all calls to methods, not explicitly defined in the filter to ist value widget.
     * Thus, the filter is a simple proxy from the point of view of the facade. However, it can be easily
     * enhanced with additional methods, that will override the ones of the value widget.
     * TODO this did not really work so far. Don't know why. As a work around, added some explicit proxy methods
     * -> __call wird aufgerufen, wenn eine un!zugreifbare Methode in einem Objekt aufgerufen wird
     * (werden die ueberschriebenen Proxymethoden entfernt, existieren sie ja aber auch noch EuiInput)
     *
     * @param string $name            
     * @param array $arguments            
     */
    public function __call($name, $arguments)
    {
        return call_user_method_array($name, $this->getInputElement(), $arguments);
    }
    
    /**
     * A filter is valid as long as it is not empty while being required - all other validations
     * like checking data type constraints do not apply as a user may search for parts of a value.
     * 
     * It is also important to validate hidden filters too because their validity is checked
     * before making lazy data requests. This is another difference compared to regular inputs
     * as used in forms, etc.
     * 
     * IDEA On the other hand, checking data type constraints might be a good idea when using
     * ceratin comparators like EQUALS or EQUALS_NOT - i.e. when partial values are not accepted.
     * 
     * @see AbstractJqueryElement::buildJsValidator()
     */
    public function buildJsValidator(?string $valJs = null) : string
    {
        $widget = $this->getWidget();
        $constraintsJs = '';
        if ($widget->isRequired() === true) {
            $constraintsJs = "if (val === undefined || val === null || val === '') { bConstraintsOK = false }";
        }
        
        $valJs = $valJs ?? $this->buildJsValueGetter();
        if ($constraintsJs !== '') {
            return <<<JS

                    (
                    (function(val){
                        var bConstraintsOK = true;
                        $constraintsJs;
                        return bConstraintsOK;
                    })($valJs) 
                    && {$this->getInputElement()->buildJsValidator()}
                    )
JS;
        } else {
            return $this->getInputElement()->buildJsValidator();
        }
    }
    
    /**
     *
     * {@inheritdoc}
     * @see AbstractJqueryElement::buildJsValidationError()
     */
    public function buildJsValidationError()
    {
        return $this->getInputElement()->buildJsValidationError();
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see AbstractJqueryElement::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        return $this->getInputElement()->buildJsResetter();
    }
    
    /**
     *
     * @param string $functionName
     * @param array $parameters
     * @return string
     */
    public function buildJsCallFunction(string $functionName = null, array $parameters = []) : string
    {
        $widget = $this->getWidget();
        if ($widget->hasFunction($functionName, false)) {
            return parent::buildJsCallFunction($functionName, $parameters);
        }
        
        return $this->getFacade()->getElement($widget->getInputWidget())->buildJsCallFunction($functionName, $parameters);
    }
}