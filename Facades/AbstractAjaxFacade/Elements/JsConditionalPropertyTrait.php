<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Widgets\Parts\ConditionalPropertyConditionGroup;

/**
 * This trait includes JS-generator methods support implementation of conditional widget properties.
 * 
 * NOTE: This trait only works if the exfTools JS library is available in the browser!
 * 
 * How to use:
 * 
 * 1) Call `registerConditionalPropertyUpdaterOnLinkedElements()` in the `init()` method of your element to
 * make sure, it is called _before_ the onChange handler of any linked widget is rendered.
 * 2) Call `buildJsConditionalProperty()` in the `buildJs()` method of your element _after_
 * the element itself is initialized. This method will set the initial value of the conditional property.
 * 
 * You will need to pass the JS code to execute if the condition is TRUE or FALSE to each of these methods.
 * 
 * @method \exface\Core\Interfaces\WidgetInterface getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JsConditionalPropertyTrait {
    
    /**
     * Generates the contents of the JS if-operator (i.e. the code between the brackets).
     * 
     * @param ConditionalProperty $conditionGroup
     * @throws FacadeRuntimeError
     * @return string
     */
    protected function buildJsConditionalPropertyIf(ConditionalPropertyConditionGroup $conditionGroup) : string
    {
        $jsConditions = [];
        
        // First evaluate the conditions
        foreach ($conditionGroup->getConditions() as $condition) {
            $leftJs = $this->buildJsConditionalPropertyValue($condition->getValueLeftExpression(), $conditionGroup->getConditionalProperty());
            $rightJs = $this->buildJsConditionalPropertyValue($condition->getValueRightExpression(), $conditionGroup->getConditionalProperty());
            
            $delim = EXF_LIST_SEPARATOR;
            // Try to get the possibly customized delimiter from the right side of the
            // condition if it is an IN-condition
            if ($condition->getComparator() === ComparatorDataType::IN || $condition->getComparator() === ComparatorDataType::NOT_IN) {
                $rightExpr = $condition->getValueRightExpression();
                if ($rightExpr->isReference() === true) {
                    $rightLink = $rightExpr->getWidgetLink($this->getWidget());
                    $targetWidget = $rightLink->getTargetWidget();
                    if (($targetWidget instanceof iShowSingleAttribute) && $targetWidget->isBoundToAttribute()) {
                        $delim = $targetWidget->getAttribute()->getValueListDelimiter();
                    } elseif ($targetWidget instanceof iHaveColumns && $colName = $rightLink->getTargetColumnId()) {
                        $targetCol = $targetWidget->getColumnByDataColumnName($colName);
                        if ($targetCol->isBoundToAttribute() === true) {
                            $delim = $targetCol->getAttribute()->getValueListDelimiter();
                        }
                    }
                }
            }
            $jsConditions[] = "exfTools.data.compareValues($leftJs, $rightJs, '{$condition->getComparator()}', '$delim')";
        }
        
        // Then just append condition groups evaluated by a recursive call to this method
        foreach ($conditionGroup->getConditionGroups() as $nestedGrp) {
            $jsConditions[] = '(' . $this->buildJsConditionalPropertyIf($nestedGrp) . ')';
        }
        
        // Now glue everything together using the logical operator
        switch ($conditionGroup->getOperator()) {
            case EXF_LOGICAL_AND: $op = ' && '; break;
            case EXF_LOGICAL_OR: $op = ' || '; break;
            default:
                throw new FacadeRuntimeError('Unsupported logical operator for conditional property "' . $conditionGroup->getPropertyName() . '" in widget "' . $this->getWidget()->getWidgetType() . ' with id "' . $this->getWidget()->getId() . '"');
        }
        
        return implode($op, $jsConditions);
    }
    
    /**
     * Generates the JS for one of the sides of a condition (`value_left` and `value_right`)
     * 
     * @param ExpressionInterface $expr
     * @param ConditionalProperty $conditionalProperty
     * @throws WidgetConfigurationError
     * @return string
     */
    protected function buildJsConditionalPropertyValue(ExpressionInterface $expr, ConditionalProperty $conditionalProperty) : string
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
            case $expr->isStatic():
                $valueJs = "'" . str_replace('"', '\"', $expr->evaluate()) . "'";
                break;
            default:
                throw new WidgetConfigurationError($conditionalProperty->getWidget(), 'Cannot use expression "' . $expr->toString() . '" in the conditional widget property "' . $conditionalProperty->getPropertyName() . '": only scalar values, static formulas and widget links supported!');
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
     * Returns a JS if-statement that performs the $ifJs or $elseJs scripts depending on
     * whether the property condition is TRUE or FALSE.
     * 
     * @param ConditionalProperty $conditionalProperty
     * @param string $ifJs
     * @param string $elseJs
     * @return string
     */
    protected function buildJsConditionalProperty(ConditionalProperty $conditionalProperty, string $ifJs, string $elseJs, bool $async = false) : string
    {
        $js = <<<JS
        
						if ({$this->buildJsConditionalPropertyIf($conditionalProperty->getConditionGroup())}) {
							{$ifJs};
						} else {
							{$elseJs};
						}
JS;
							
		if ($async === true) {
		    $js .= <<<JS
		    
                    setTimeout(function(){
                        {$js}
                    }, 0);
JS;
		}
							
		return $js;
    }
}