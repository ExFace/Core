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
 * Use this trait in a facade element representing a widget, that support disable_condition.
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
trait JqueryDisableConditionTrait {
    
    protected function buildJsConditionalPropertyIf(ConditionalProperty $conditionalProperty) : string
    {
        $jsConditions = [];
        foreach ($conditionalProperty->getConditions() as $condition) {
            $leftJs = $this->buildJsConditionalPropertyValue($condition->getValueLeftExpression(), $conditionalProperty);
            $rightJs = $this->buildJsConditionalPropertyValue($condition->getValueRightExpression(), $conditionalProperty);
            
            switch ($condition->getComparator()) {
                case EXF_COMPARATOR_IS_NOT: // !=
                case EXF_COMPARATOR_EQUALS: // ==
                case EXF_COMPARATOR_EQUALS_NOT: // !==
                case EXF_COMPARATOR_LESS_THAN: // <
                case EXF_COMPARATOR_LESS_THAN_OR_EQUALS: // <=
                case EXF_COMPARATOR_GREATER_THAN: // >
                case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS: // >=
                    // Man muesste eigentlich schauen ob ein bestimmter Wert vorhanden ist: buildJsValueGetter(link->getTargetColumnId()).
                    // Da nach einem Prefill dann aber normalerweise ein leerer Wert zurueckkommt, wird beim initialisieren
                    // momentan einfach geschaut ob irgendein Wert vorhanden ist.
                    $jsConditions[] = "$leftJs {$condition->getComparator()} $rightJs";
                    break;
                case EXF_COMPARATOR_IN: // [
                case EXF_COMPARATOR_NOT_IN: // ![
                case EXF_COMPARATOR_IS: // =
                default:
                    // TODO fuer diese Comparatoren muss noch der JavaScript generiert werden
            }
        }
        
        switch ($conditionalProperty->getOperator()) {
            case EXF_LOGICAL_AND: $op = ' && '; break;
            case EXF_LOGICAL_OR: $op = ' || '; break;
            default:
                throw new FacadeRuntimeError('Unsupported logical operator for conditional property "' . $conditionalProperty->getPropertyName() . '" in widget "' . $this->getWidget()->getWidgetType() . ' with id "' . $this->getWidget()->getId() . '"');
        }
        
        return implode($op, $jsConditions);
    }
    
    /**
     * 
     * @param ExpressionInterface $expr
     * @param ConditionalProperty $conditionalProperty
     * @throws WidgetConfigurationError
     * @return string
     */
    private function buildJsConditionalPropertyValue(ExpressionInterface $expr, ConditionalProperty $conditionalProperty) : string
    {
        switch (true) {
            case $expr->isReference() === true:
                $link = $expr->getWidgetLink($conditionalProperty->getWidget());
                if ($linked_element = $this->getFacade()->getElement($link->getTargetWidget())) {
                    $valueJs = $linked_element->buildJsValueGetter($link->getTargetColumnId());
                }
                break;
            case $expr->isFormula() === false && $expr->isMetaAttribute() === false:
                $valueJs = "'" . str_replace('"', '\"', $expr->toString()) . "'";
                break;
            default:
                throw new WidgetConfigurationError($conditionalProperty->getWidget(), 'Cannot use expression "' . $expr->toString() . '" in the conditional widget property "' . $conditionalProperty->getPropertyName() . '": only scalar values and widget links supported!');
        }
        
        return $valueJs;
    }
    
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
        
        return <<<JS
        
						if ({$this->buildJsConditionalPropertyIf($conditionalProperty)}) {
							{$this->buildJsDisabler()};
						} else {
							{$enable_widget_script}
						}
						
JS;
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
     * @return \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryLiveReferenceTrait
     */
    protected function registerDisableConditionAtLinkedElement()
    {
        if (($conditionalProperty = $this->getWidget()->getDisabledIf()) === null) {
            return;
        }
        foreach ($conditionalProperty->getConditions() as $condition) {
            if ($condition->getValueLeftExpression()->isReference() === true) {
                $link = $condition->getValueLeftExpression()->getWidgetLink($condition->getWidget());
                if ($linked_element = $this->getFacade()->getElement($link->getTargetWidget())) {
                    $linked_element->addOnChangeScript($this->buildJsDisableCondition());
                }
            }
            if ($condition->getValueRightExpression()->isReference() === true) {
                $link = $condition->getValueRightExpression()->getWidgetLink($condition->getWidget());
                if ($linked_element = $this->getFacade()->getElement($link->getTargetWidget())) {
                    $linked_element->addOnChangeScript($this->buildJsDisableCondition());
                }
            }
        }
        return $this;
    }
}