<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Widgets\Filter;
use exface\Core\Widgets\Input;
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
            $comparator = $condition->getComparator();
            $leftJs = $this->buildJsConditionalPropertyValue($condition->getValueLeftExpression(), $conditionGroup->getConditionalProperty());
            $rightJs = $this->buildJsConditionalPropertyValue($condition->getValueRightExpression(), $conditionGroup->getConditionalProperty());
            
            $leftExpr = $condition->getValueLeftExpression();
            $leftTargetWidget = $leftExpr->isReference() ? $leftExpr->getWidgetLink($this->getWidget())->getTargetWidget() : null;
            $rightExpr = $condition->getValueRightExpression();
            $rightTargetWidget = $rightExpr->isReference() ? $rightExpr->getWidgetLink($this->getWidget())->getTargetWidget() : null;
            
            $delim = EXF_LIST_SEPARATOR;
            // Try to get the possibly customized delimiter from the right side of the
            // condition if it is an IN-condition
            if ($comparator === ComparatorDataType::IN || $comparator === ComparatorDataType::NOT_IN) {
                if ($rightTargetWidget !== null) {
                    if (($rightTargetWidget instanceof iShowSingleAttribute) && $rightTargetWidget->isBoundToAttribute()) {
                        $delim = $rightTargetWidget->getAttribute()->getValueListDelimiter();
                    } elseif ($rightTargetWidget instanceof iHaveColumns && $colName = $rightExpr->getWidgetLink($this->getWidget())->getTargetColumnId()) {
                        $targetCol = $rightTargetWidget->getColumnByDataColumnName($colName);
                        if ($targetCol->isBoundToAttribute() === true) {
                            $delim = $targetCol->getAttribute()->getValueListDelimiter();
                        }
                    }
                }
            }
            
            // Check if either expression is a reference to a widget, that supports multi-select or similar features.
            // In those cases, the comparator needs to be a list comparator, in case more than one data-set must
            // be evaluated.
            // TODO Filters support multi-select, but do not expose that information. How to handle this?
            $requiresListComparator =
                $leftTargetWidget instanceof iSupportMultiSelect ||
                ($leftTargetWidget instanceof Input && $leftTargetWidget->getMultipleValuesAllowed()) ||
                $rightTargetWidget instanceof iSupportMultiSelect ||
                ($rightTargetWidget instanceof Input && $rightTargetWidget->getMultipleValuesAllowed());

            if ($requiresListComparator === true) {
                $comparator = ComparatorDataType::convertToListComparator($comparator) ?? $comparator;
            }
            
            $jsConditions[] = "exfTools.data.compareValues($leftJs, $rightJs, '{$comparator}', '$delim')";
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
            // For widget links use the value getter of the linked facade element
            case $expr->isReference() === true:
                $link = $expr->getWidgetLink($conditionalProperty->getWidget());
                if ($linked_element = $this->getFacade()->getElement($link->getTargetWidget())) {
                    $valueJs = $linked_element->buildJsValueGetter($link->getTargetColumnId());
                }
                break;
            // If it is a constant (string, number or boolean), take the unquoted value and quote properly
            // for JS if needed
            case $expr->isConstant() === true:
                $value = $expr->evaluate();
                switch (true) {
                    case is_bool($value):
                    case is_numeric($value):
                        $valueJs = $value;
                        break;
                    default:
                        $valueJs = $this->escapeString($value);
                        break;
                }
                break;
            // Keep other types of expressions as-is as quoted JS string.
            // TODO we use single quotes here, but escape double quotes too - why? 
            case $expr->isFormula() === false && $expr->isMetaAttribute() === false:
                $valueJs = "'" . str_replace(['"', "'"], ['\"', "\\'"], $expr->__toString()) . "'";
                break;
            // Evaluate static formulas
            // TODO same problem with strage escaping here. Why?
            case $expr->isStatic():
                $valueJs = "'" . str_replace(['"', "'"], ['\"', "\\'"], $expr->evaluate()) . "'";
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