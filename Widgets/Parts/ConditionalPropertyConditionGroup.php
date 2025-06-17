<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;


/**
 * Condition group representation for conditional properties.
 * 
 * @see ConditionalProperty
 * 
 * @author Andrej Kabachnik
 * 
 */
class ConditionalPropertyConditionGroup implements WidgetPartInterface, \Stringable
{
    use ImportUxonObjectTrait;
    
    private $conditionalProperty = null;
    
    private $operator = null;
    
    private $conditions = [];
    
    private $conditionGroups = [];
    
    /**
     * 
     * @param WidgetInterface $widget
     * @param string $propertyName
     * @param UxonObject $uxon
     */
    public function __construct(ConditionalProperty $property, UxonObject $uxon)
    {
        $this->conditionalProperty = $property;
        $this->importUxonObject($uxon);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            'operator' => $this->getOperator()
        ]);
        
        foreach ($this->getConditions() as $condition) {
            $uxon->appendToProperty('conditions', $condition->exportUxonObject());
        }
        
        foreach ($this->getConditionGroups() as $condGrp) {
            $uxon->appendToProperty('condition_groups', $condGrp->exportUxonObject());
        }
        
        return $uxon;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget() : WidgetInterface
    {
        return $this->conditionalProperty->getWidget();
    }
    
    /**
     * 
     * @return MetaObjectInterface
     */
    protected function getBaseObject() : MetaObjectInterface
    {
        return $this->conditionalProperty->getBaseObject();
    }
    
    /**
     *
     * @return string
     */
    public function getOperator() : string
    {
        return $this->operator ?? EXF_LOGICAL_AND;
    }
    
    /**
     * Logical operator used to combine conditions (e.g. AND, OR, etc.)
     * 
     * @uxon-property operator
     * @uxon-type [AND,OR]
     * @uxon-default AND
     * 
     * @param string $value
     * @return ConditionalPropertyConditionGroup
     */
    protected function setOperator(string $value) : ConditionalPropertyConditionGroup
    {
        $this->operator = $value;
        return $this;
    }
    
    /**
     * 
     * @return ConditionalPropertyCondition[]
     */
    public function getConditions() : array
    {
        return $this->conditions;
    }
    
    /**
     * 
     * @return ConditionalPropertyConditionGroup[]
     */
    public function getConditionsRecursive() : array
    {
        $array = $this->getConditions();
        foreach ($this->getConditionGroups() as $grp) {
            $array = array_merge($array, $grp->getConditionsRecursive());
        }
        return $array;
    }
    
    /**
     * Array of conditions combined by the logical operator of this group.
     * 
     * @uxon-property conditions
     * @uxon-type \exface\Core\Widgets\Parts\ConditionalPropertyCondition[]
     * @uxon-template [{"value_left": "", "comparator": "", "value_right": ""}]
     * 
     * @param UxonObject $uxon
     * @return ConditionalPropertyConditionGroup
     */
    protected function setConditions(UxonObject $uxon) : ConditionalPropertyConditionGroup
    {
        foreach ($uxon as $condUxon) {
            $this->addCondition(new ConditionalPropertyCondition($this->conditionalProperty, $condUxon));
        }
        return $this;
    }
    
    /**
     * 
     * @param ConditionalPropertyCondition $condition
     * @return ConditionalPropertyConditionGroup
     */
    public function addCondition(ConditionalPropertyCondition $condition) : ConditionalPropertyConditionGroup
    {
        $this->conditions[] = $condition;
        return $this;
    }
    
    public function getConditionGroups() : array
    {
        return $this->conditionGroups;
    }
    
    protected function setConditionGroups(UxonObject $uxon) : ConditionalPropertyConditionGroup
    {
        foreach ($uxon as $grpUxon) {
            $this->addConditionGroup(new self($this->conditionalProperty, $grpUxon));
        }
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function hasNestedGroups() : bool
    {
        return ! empty($this->conditionGroups);
    }
    
    public function addConditionGroup(ConditionalPropertyConditionGroup $group) : ConditionalPropertyConditionGroup
    {
        $this->conditionGroups[] = $group;
        return $this;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->conditionalProperty->getWorkbench();
    }

    /**
     * 
     * @return ConditionalProperty
     */
    public function getConditionalProperty() : ConditionalProperty
    {
        return $this->conditionalProperty;
    }

    public function __toString(): string
    {
        $result = '';
        foreach ($this->getConditions() as $cond) {
            $result .= ($result ? ' ' . $this->getOperator() . ' ' : '') . $cond->__toString();
        }
        foreach ($this->getConditionGroups() as $group) {
            $result .= ($result ? ' ' . $this->getOperator() . ' ' : '') . '( ' . $group->__toString() . ' )';
        }
        return $result;
    }
}