<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;


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
        // TODO remove non-conditional UXON stuff
        $condGrpUxon->unsetProperty('reset_widget_on_change');
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
        
        // TODO add non-conditional UXON stuff
        
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
     * 
     * @return bool
     */
    public function hasResetWidgetOnChange() : ?bool
    {
        return $this->resetOnChange;
    }
    
    /**
     * Set to TRUE or FALSE to force-reset (e.g. empty) the widget every time the condition changes
     * 
     * If not set, facades will determine the behavior automatically: e.g. `hidden_if` will
     * reset the widget when hiding/showing it while `required_if` will not.
     * 
     * @uxon-property reset_widget_on_change
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return ConditionalProperty
     */
    public function setResetWidgetOnChange(bool $value) : ConditionalProperty
    {
        $this->resetOnChange = $value;
        return $this;
    }
}