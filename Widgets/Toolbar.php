<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iContainButtonGroups;
use exface\Core\CommonLogic\UxonObject;

/**
 * A button bar displays one or more button groups as a toolbar.
 *
 * @author Andrej Kabachnik
 *        
 */
class Toolbar extends ButtonGroup implements iContainButtonGroups
{
    const POSITION_DEFAULT = 'default';
    
    const POSITION_TOP = 'top';
    
    const POSITION_BOTTOM = 'bottom';
    
    const POSITION_MENU = 'menu';
    
    private $position = null;
    
    private $button_groups = array();
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainButtonGroups::getButtonGroups()
     */
    public function getButtonGroups()
    {
        if (count($this->button_groups) == 0){
            $this->button_groups[] = WidgetFactory::create($this->getPage(), 'ButtonGroup', $this);
        }
        return $this->button_groups;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainButtonGroups::setButtonGroups()
     */
    public function setButtonGroups(array $button_groups_or_uxon_arrays)
    {
        foreach ($button_groups_or_uxon_arrays as $group){
            if ($group instanceof ButtonGroup){
                $this->addButtonGroup($group);
            } elseif ($group instanceof UxonObject){
                $this->addButton(WidgetFactory::createFromUxon($this->getPage(), $group, $this));
            }
        }
        return $this;
    }
    
    /**
     * 
     * @param ButtonGroup $button_group
     * @return \exface\Core\Widgets\Toolbar
     */
    public function addButtonGroup(ButtonGroup $button_group)
    {
        if ($button_group->getParent() !== $this){
            $button_group->setParent($this);
        }
        $this->button_groups[] = $button_group;
        return $this;
    }
    
    /**
     * 
     * @param ButtonGroup $button_group
     * @return \exface\Core\Widgets\Toolbar
     */
    public function removeButtonGroup(ButtonGroup $button_group)
    {
        unset($this->button_groups[array_search($button_group, $this->button_groups)]);
        return $this;
    }
    
    /**
     * 
     * @return ButtonGroup
     */
    public function getButtonGroupMain()
    {
        return $this->getButtonGroups()[0];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\ButtonGroup::addButton()
     */
    public function addButton(Button $button_widget)
    {
        $this->getButtonGroupMain()->addButton($button_widget);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\ButtonGroup::removeButton()
     */
    public function removeButton(Button $button_widget)
    {
        foreach ($this->getButtonGroups() as $grp){
            $grp->removeButton($button_widget);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\ButtonGroup::getButtons()
     */
    public function getButtons()
    {
        $buttons = [];
        foreach ($this->getButtonGroups() as $grp){
            $buttons = array_merge($buttons, $grp->getButtons());
        }
        return $buttons;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\ButtonGroup::setButtons()
     */
    public function setButtons(array $buttons)
    {
        $this->getButtonGroupMain()->setButtons($buttons);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\ButtonGroup::hasButtons()
     */
    public function hasButtons()
    {
        foreach ($this->getButtonGroups() as $grp){
            if ($grp->hasButtons()){
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\ButtonGroup::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return $this->getInputWidget()->getButtonWidgetType();
    }
    
    /**
     * 
     * @param string $position
     * @return \exface\Core\Widgets\Toolbar
     */
    public function setPosition($position)
    {
        if (defined(get_class() . '::POSITION_' . mb_strtoupper($position))) {
            $this->position = constant(get_class() . '::POSITION_' . mb_strtoupper($position));
        } else {
            $this->position = $position;
        }
        
        return $this;
    }
    
}
?>