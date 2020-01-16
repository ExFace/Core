<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\WidgetInterface;

/**
 * This trait includes JS-generator methods support implementation of conditional widget properties.
 * 
 * How to use:
 * 
 * 1) Call `registerConditionalPropertyUpdaterOnLinkedElements()` in the `init()` method of your element to
 * make sure, it is called _before_ the onChange handler of any linked widget is rendered.
 * 2) Call `buildJsDisableConditionInitializer()` in the `buildJs()` method of your element _after_
 * the element itself is initialized. This method will set the initial value of the conditional property.
 * 
 * You will need to pass the JS code to execute if the condition is TRUE or FALSE to each of these methods.
 * 
 * @method WidgetInterface getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JsConditionalPropertyTrait {
    
    /**
     * Generates the contents of the JS if-operator (i.e. the code between the brackets).
     * 
     * @param ConditionalProperty $conditionalProperty
     * @throws FacadeRuntimeError
     * @return string
     */
    private function buildJsConditionalPropertyIf(ConditionalProperty $conditionalProperty) : string
    {
        $jsConditions = [];
        foreach ($conditionalProperty->getConditions() as $condition) {
            $leftJs = $this->buildJsConditionalPropertyValue($condition->getValueLeftExpression(), $conditionalProperty);
            $rightJs = $this->buildJsConditionalPropertyValue($condition->getValueRightExpression(), $conditionalProperty);
            
            switch ($condition->getComparator()) {
                case EXF_COMPARATOR_EQUALS: // ==
                case EXF_COMPARATOR_EQUALS_NOT: // !==
                case EXF_COMPARATOR_LESS_THAN: // <
                case EXF_COMPARATOR_LESS_THAN_OR_EQUALS: // <=
                case EXF_COMPARATOR_GREATER_THAN: // >
                case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS: // >=
                    $jsConditions[] = "$leftJs {$condition->getComparator()} $rightJs";
                    break;
                case EXF_COMPARATOR_IN: // [
                    $condition = $this->buildJsConditionalPropertyComparatorIn($leftJs, $rightJs);
                    $jsConditions[] = $condition;
                    break;
                case EXF_COMPARATOR_NOT_IN: // ![
                    $condition = "!(";
                    $condition .= $this->buildJsConditionalPropertyComparatorIn($leftJs, $rightJs);
                    $condition .= ")";
                    $jsConditions[] = $condition;
                    break;
                case EXF_COMPARATOR_IS: // =
                    $condition = "(new RegExp(($rightJs || '').toString(), 'i')).test(({$leftJs} || '').toString())";
                    $jsConditions[] = $condition;
                    break;
                case EXF_COMPARATOR_IS_NOT: // !=
                    $condition = "!((new RegExp(($rightJs || '').toString(), 'i')).test(({$leftJs} || '').toString()))";;
                    $jsConditions[] = $condition;
                    break;
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
     * Build the javascript function for the EXF_COMPARATOR_IN comparator.
     * 
     * @param string $leftJs
     * @param string $rightJs
     * @return string
     */
    private function buildJsConditionalPropertyComparatorIn (string $leftJs, string $rightJs) : string
    {
        $delim = EXF_LIST_SEPARATOR;
        $comparator = EXF_COMPARATOR_EQUALS;
        $condition = <<<JS

            function() {
                var rightValues = (({$rightJs} || '').toString()).split('{$delim}');
                for (var i = 0; i < rightValues.length; i++) {
                    if (({$leftJs} || '').toString() {$comparator} rightValues[i].trim()) {
                        return true;
                    }
                }
                return false;
            }()

JS;
        return $condition;
    }
    
    /**
     * Generates the JS for one of the sides of a condition (`value_left` and `value_right`)
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
     * Hooks the $updateScriptJs to change-events of every facade element referenced in the conditions.
     * 
     * @param ConditionalProperty $conditionalProperty
     * @param string $ifJs
     * @param string $elseJs
     * @return void
     */
    protected function registerConditionalPropertyUpdaterOnLinkedElements(ConditionalProperty $conditionalProperty, string $ifJs, string $elseJs)
    {
        foreach ($conditionalProperty->getConditions() as $condition) {
            if ($condition->getValueLeftExpression()->isReference() === true) {
                $link = $condition->getValueLeftExpression()->getWidgetLink($condition->getWidget());
                if ($linked_element = $this->getFacade()->getElement($link->getTargetWidget())) {
                    $linked_element->addOnChangeScript($this->buildJsConditionalProperty($conditionalProperty, $ifJs, $elseJs));
                }
            }
            if ($condition->getValueRightExpression()->isReference() === true) {
                $link = $condition->getValueRightExpression()->getWidgetLink($condition->getWidget());
                if ($linked_element = $this->getFacade()->getElement($link->getTargetWidget())) {
                    $linked_element->addOnChangeScript($this->buildJsConditionalProperty($conditionalProperty, $ifJs, $elseJs));
                }
            }
        }
        return;
    }
    
    /**
     * Returns JS-code to set the initial state of the conditional property.
     *
     * @param ConditionalProperty $conditionalProperty
     * @param string $ifJs
     * @param string $elseJs
     * @return string
     */
    protected function buildJsConditionalPropertyInitializer(ConditionalProperty $conditionalProperty, string $ifJs, string $elseJs) : string
    {
        return <<<JS
        
                    setTimeout(function(){
                        {$this->buildJsConditionalProperty($conditionalProperty, $ifJs, $elseJs)}
                    }, 0);

JS;
    }
    
    /**
     * Returns a JS if-statement that performs the $ifJs or $elseJs scripts depending on
     * whether the property condition is TRUE or FALSE.
     * 
     * @param ConditionalProperty $conditionalProperty
     * @param string $ifJs
     * @param string $elseJs
     * @return string
     */
    protected function buildJsConditionalProperty(ConditionalProperty $conditionalProperty, string $ifJs, string $elseJs) : string
    {
        return <<<JS
        
						if ({$this->buildJsConditionalPropertyIf($conditionalProperty)}) {
							{$ifJs};
						} else {
							{$elseJs};
						}
						
JS;
    }
}