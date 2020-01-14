<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Widgets\iCanBeDisabled;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * This trait includes JS-generator methods to make an control disabled on certain conditions.
 * 
 * NOTE: this is just a wrapper for the JsConditionalPropertyTrait for backwards-compatibility.
 * Do not copy it for other conditional properties! Instead, use the JsConditionalPropertyTrait
 * directly - see implementation of `required_if` properties for an example.
 * 
 * Use this trait in a facade element representing a widget, that support `disabled_if` property.
 * 
 * How to use:
 * 
 * 1) Call registerDisableConditionAtLinkedElement() in the init() method of your element to
 * make sure, it is called _before_ the onChange handler of the linked widget is rendered.
 * 2) Call buildJsDisableConditionInitializer() in the buildJs() method of your element _after_
 * the element itself is initialized. This method will call the JS disabler if your element
 * needs to be disabled initially.
 * 3) Make sure, the methods buildJsEnabler() and buildJsDisabler produce code suitable for
 * your element. These methods are likely to be inherited, so doublechek ther return values.
 * 
 * @method iCanBeDisabled getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryDisableConditionTrait 
{
    
    use JsConditionalPropertyTrait;
    
    /**
    * Returns a JavaScript-snippet, which is registered in the onChange-Script of the
    * element linked by the disable condition.
    * Based on the condition and the value
    * of the linked widget, it enables and disables the current widget.
    *
    * @return string
    */
    protected function buildJsDisableCondition() : string
    {
        $widget = $this->getWidget();
        
        if (($conditionalProperty = $widget->getDisabledIf()) === null) {
            return '';
        }
        
        $enable_widget_script = $widget->isDisabled() ? '' : $this->buildJsEnabler() . ';';
        
        return $this->buildJsConditionalProperty($conditionalProperty, $this->buildJsDisabler(), $enable_widget_script);
    }
    
    /**
     * Returns a JavaScript-snippet, which initializes the disabled-state of elements
     * with a disabled condition.
     *
     * @return string
     */
    protected function buildJsDisableConditionInitializer() : string
    {
        if (($conditionalProperty = $this->getWidget()->getDisabledIf()) === null) {
            return '';
        }
        
        return <<<JS
        
                        setTimeout(function(){
                            if ({$this->buildJsConditionalPropertyIf($conditionalProperty)}) {
    							{$this->buildJsDisabler()};
    						}
                        }, 0);
JS;
    }

    /**
     * Registers an onChange-Skript on the element linked by the disable condition.
     *
     * @return AbstractJqueryElement
     */
    protected function registerDisableConditionAtLinkedElement() : AbstractJqueryElement
    {
        if (($conditionalProperty = $this->getWidget()->getDisabledIf()) === null) {
            return $this;
        }
        
        $this->registerConditionalPropertyUpdaterOnLinkedElements($conditionalProperty, $this->buildJsDisabler(), $this->getWidget()->isDisabled() ? '' : $this->buildJsEnabler() . ';');
        
        return $this;
    }
}