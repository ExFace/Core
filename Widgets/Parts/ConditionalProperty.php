<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;


/**
 * The value of a conditional widget property is defined by one or more conditions.
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
    
    private $propertyName = null;
    
    private $operator = null;
    
    private $conditions = [];
    
    /**
     * 
     * @param WidgetInterface $widget
     * @param string $propertyName
     * @param UxonObject $uxon
     */
    public function __construct(WidgetInterface $widget, string $propertyName, UxonObject $uxon)
    {
        $this->widget = $widget;
        $this->propertyName = $propertyName;
        $this->importUxonObject($uxon);
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
            $this->addCondition($this->createCondition($uxon));
            return;
        } 
        
        // Legacy syntax of the old disable_condition property
        if ($uxon->hasProperty('widget_link') === true) {
            $condition = $this->createCondition(new UxonObject([
                'value_left' => '=' . $uxon->getProperty('widget_link'),
                'comparator' => $uxon->getProperty('comparator'),
                'value_right' => $uxon->getProperty('value')
            ]));
            $this->addCondition($condition);
            return;
        }
        
        // Regular syntax with a condition group
        $this->importUxonObjectViaTrait($uxon, $skip_property_names);
        return;
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
        
        return $uxon;
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
     * @return ConditionalProperty
     */
    public function setOperator(string $value) : ConditionalProperty
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
     * Array of conditions combined by the logical operator of this group.
     * 
     * @uxon-property conditions
     * @uxon-type \exface\Core\Widgets\Parts\ConditionalPropertyCondition[]
     * @uxon-template [{"value_left": "", "comparator": "", "value_right": ""}]
     * 
     * @param UxonObject $uxon
     * @return ConditionalProperty
     */
    public function setConditions(UxonObject $uxon) : ConditionalProperty
    {
        foreach ($uxon as $condUxon) {
            $this->addCondition($this->createCondition($condUxon));
        }
        return $this;
    }
    
    /**
     * 
     * @param ConditionalPropertyCondition $condition
     * @return ConditionalProperty
     */
    protected function addCondition(ConditionalPropertyCondition $condition) : ConditionalProperty
    {
        $this->conditions[] = $condition;
        return $this;
    }
    
    /**
     * 
     * @param UxonObject $uxon
     * @return ConditionalPropertyCondition
     */
    protected function createCondition(UxonObject $uxon = null) : ConditionalPropertyCondition
    {
        $cond = new ConditionalPropertyCondition($this);
        if ($uxon !== null) {
            $cond->importUxonObject($uxon);
        }
        return $cond;
    }
}