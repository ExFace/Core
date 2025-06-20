<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Widgets\Input;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Widgets\WidgetLogicError;


/**
 * The value of a conditional widget property is defined by one or more conditions and/or condition groups.
 * 
 * The conditional property is very similar to a condition group in the model, however it
 * is tailored to work with live references in facades: in other words, the left and
 * rigth values are computed in a different way than that of the condition group.
 * 
 * @see ConditionalPropertyCondition
 * @see ConditionalPropertyConditionGroup
 * 
 * @author Andrej Kabachnik
 * 
 */
class ConditionalProperty implements WidgetPartInterface
{
    use ImportUxonObjectTrait {
        importUxonObject as importUxonObjectViaTrait;
    }
    
    private $widget = null;
    
    private $baseObject = null;
    
    private $propertyName = null;
    
    private $conditionGroup = null;
    
    private $resetOnChange = null;
    
    private $onTrue = null;
    
    private $onFalse = null;
    
    private $reason = null;
    
    /**
     * 
     * @param WidgetInterface $widget
     * @param string $propertyName
     * @param UxonObject $uxon
     */
    public function __construct(WidgetInterface $widget, string $propertyName, UxonObject $uxon, MetaObjectInterface $baseObject = null)
    {
        $this->widget = $widget;
        $this->baseObject = $baseObject ?? $widget->getMetaObject();
        $this->propertyName = $propertyName;
        $this->importUxonObject($uxon);
    }
    
    /**
     * 
     * @return ConditionalPropertyConditionGroup
     */
    public function getConditionGroup() : ConditionalPropertyConditionGroup
    {
        return $this->conditionGroup;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon, array $skip_property_names = array())
    {
        // Short notation with only a condition
        if ($uxon->hasProperty('value_left') === true || $uxon->hasProperty('value_right') === true) {
            $this->conditionGroup = new ConditionalPropertyConditionGroup($this, new UxonObject([
                'operator' => EXF_LOGICAL_AND,
                'conditions' => [$uxon->toArray()]
            ]));
            return;
        } 
        
        // Legacy syntax of the old disable_condition property
        if ($uxon->hasProperty('widget_link') === true) {
            $this->conditionGroup = new ConditionalPropertyConditionGroup($this, new UxonObject([
                'operator' => EXF_LOGICAL_AND,
                'conditions' => [
                    [
                        'value_left' => '=' . $uxon->getProperty('widget_link'),
                        'comparator' => $uxon->getProperty('comparator'),
                        'value_right' => $uxon->getProperty('value')
                    ]
                ]
            ]));
            return;
        }
        
        $condGrpUxon = $uxon->copy();
        $condGrpUxon->unsetProperty('empty_widget_on_change');
        $condGrpUxon->unsetProperty('function_on_true');
        $condGrpUxon->unsetProperty('function_on_false');
        $condGrpUxon->unsetProperty('reason');
        $this->conditionGroup = new ConditionalPropertyConditionGroup($this, $condGrpUxon);
        
        $this->importUxonObjectViaTrait($uxon, ['conditions', 'condition_groups', 'operator']);
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = $this->getConditionGroup()->exportUxonObject();
        
        if (null !== $val = $this->getFunctionOnTrue()) {
            $uxon->setProperty('function_on_true', $val);
        }
        if (null !== $val = $this->getFunctionOnFalse()) {
            $uxon->setProperty('function_on_false', $val);
        }
        if (null !== $val = $this->getReason()) {
            $uxon->setProperty('reason', $val);
        }
        
        return $uxon;
    }
    
    /**
     * Logical operator of the group: AND, OR, etc.
     * 
     * @uxon-property operator
     * @uxon-type [AND,OR,XOR]
     * 
     * @param string $logicalOp
     * @throws WidgetLogicError
     * @return ConditionalProperty
     */
    protected function setOperator(string $logicalOp) : ConditionalProperty
    {
        $logicalOp = mb_strtoupper($logicalOp);
        if ($this->conditionGroup === null) {
            $this->conditionGroup = new ConditionalPropertyConditionGroup($this, new UxonObject([
                'operator' => $logicalOp
            ]));
        } elseif ($this->conditionGroup->getOperator() !== $logicalOp) {
            throw new WidgetLogicError($this->getWidget(), 'Cannot change the logical operator of a conditional property after it has been initialized!');
        }
        return $this;
    }
    
    /**
     * Array of conditions
     *
     * @uxon-property conditions
     * @uxon-type \exface\Core\Widgets\Parts\ConditionalPropertyCondition[]
     * @uxon-template [{"value_left": "", "comparator": "==", "value_right": ""}]
     *
     * @param UxonObject $arrayOfConditions
     * @throws WidgetLogicError
     * @return ConditionalProperty
     */
    protected function setConditions(UxonObject $arrayOfConditions) : ConditionalProperty
    {
        if ($this->conditionGroup === null) {
            $this->conditionGroup = new ConditionalPropertyConditionGroup($this, new UxonObject([
                'conditions' => $arrayOfConditions->toArray()
            ]));
        } else {
            $this->conditionGroup = new ConditionalPropertyConditionGroup(
                $this,
                $this->conditionGroup->exportUxonObject()->extend(new UxonObject([
                    'conditions' => $arrayOfConditions->toArray()
                ]))
            );
        }
        return $this;
    }
    
    /**
     * Groups of conditions with their own logical operators - e.g. `AND(cond2, cond3)`
     *
     * @uxon-property condition_groups
     * @uxon-type \exface\Core\Widgets\Parts\ConditionalPropertyConditionGroup[]
     * @uxon-template [{"operator": "","conditions": [{"value_left": "", "comparator": "==", "value_right": ""}]}]
     * 
     * @param UxonObject $arrayOfCondGroups
     * @throws WidgetLogicError
     * @return ConditionalProperty
     */
    protected function setConditionGroups(UxonObject $arrayOfCondGroups) : ConditionalProperty
    {
        if ($this->conditionGroup === null) {
            $this->conditionGroup = new ConditionalPropertyConditionGroup($this,  UxonObject([
                'condition_groups' => $arrayOfCondGroups->toArray()
            ]));
        } else {
            $this->conditionGroup = new ConditionalPropertyConditionGroup(
                $this,
                $this->conditionGroup->exportUxonObject()->extend(new UxonObject([
                    'condition_groups' => $arrayOfCondGroups->toArray()
                ])),
            );
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget() : WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * 
     * @return MetaObjectInterface
     */
    public function getBaseObject() : MetaObjectInterface
    {
        return $this->baseObject ?? $this->getWidget()->getMetaObject();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }
    
    /**
     * Returns the name of the widget property where the conditions are used on.
     * 
     * @return string
     */
    public function getPropertyName() : string
    {
        return $this->propertyName;
    }
    
    /**
     * Returns a flat array with all conditions used in this property - accross all groups!
     * 
     * @return ConditionalPropertyCondition[]
     */
    public function getConditions() : array
    {
        return $this->getConditionGroup()->getConditionsRecursive();
    }
    
    /**
     * @deprecated use setFunctionOnTrue() and setFunctionOnFalse() instead!
     * 
     * @param bool $value
     * @return ConditionalProperty
     */
    private function setEmptyWidgetOnChange(bool $value) : ConditionalProperty
    {
        $this->setFunctionOnTrue(Input::FUNCTION_EMPTY);
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getReason() : ?string
    {
        return $this->reason;
    }
    
    /**
     * Description of what this means for the user - i.e. WHY a `xxx_if` property was applied in simple words
     * 
     * @uxon-property reason
     * @uxon-type string
     * 
     * @param string $value
     * @return ConditionalProperty
     */
    public function setReason(string $value) : ConditionalProperty
    {
        $this->reason = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getFunctionOnTrue() : ?string
    {
        return $this->onTrue;
    }
    
    /**
     * A widget function (e.g. `reset`) to be called after this condition evaluates to TRUE
     * 
     * For example, you can force an input to be emptied in its `disabled_if` condition.
     * 
     * @uxon-property function_on_true
     * @uxon-type metamodel:widget_function
     * 
     * @param string $widgetFunction
     * @return ConditionalProperty
     */
    public function setFunctionOnTrue(string $widgetFunction) : ConditionalProperty
    {
        $this->onTrue = $widgetFunction;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getFunctionOnFalse() : ?string
    {
        return $this->onFalse;
    }
    
    /**
     * A widget function (e.g. `reset`) to be called after this condition evaluates to FALSE
     * 
     * @uxon-property function_on_false
     * @uxon-type metamodel:widget_function
     * 
     * @param string $widgetFunction
     * @return ConditionalProperty
     */
    public function setFunctionOnFalse(string $widgetFunction) : ConditionalProperty
    {
        $this->onFalse = $widgetFunction;
        return $this;
    }
    
    /**
     * Returns TRUE if any of the conditions use live references or FALSE otherwise.
     * 
     * @return bool
     */
    public function hasWidgetLinks() : bool
    {
        foreach ($this->getConditionGroup()->getConditionsRecursive() as $cond) {
            if ($cond->getValueLeftExpression()->isReference() === true) {
                return true;
            }
            if ($cond->getValueRightExpression()->isReference() === true) {
                return true;
            }
        }
        return false;
    }
}