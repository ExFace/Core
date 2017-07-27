<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iContainButtonGroups;
use exface\Core\CommonLogic\UxonObject;

/**
 * Toolbars are used to organize buttons within widgets.
 * 
 * Each toolbar contains one or more button groups, which can be aligned
 * left or right within the toolbar. The following schematic illustration 
 * shows a toolbar with three button groups. Note, that grp2 has been 
 * aligned to the right. 
 * 
 * ------------------------------------------------------------------------
 * | [grp1_btn1] [grp1_btn2] | [grp3_btn1] |    | [grp2_btn1] [grp2_btn2] |
 * ------------------------------------------------------------------------
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
            $this->button_groups[] = $this->createButtonGroup();
        }
        return $this->button_groups;
    }
    
    /**
     * 
     * @return ButtonGroup
     */
    public function createButtonGroup()
    {
        return WidgetFactory::create($this->getPage(), 'ButtonGroup', $this)->setAlign(EXF_ALIGN_DEFAULT);
    }
    
    /**
     * Defines the button groups in this toolbar via array of button group widgets.
     * 
     * Each element of the array must be a ButtonGroup widget object or a
     * derivative. The widget type can also be ommitted. In this case, ButtonGroup
     * will be assumed.
     * 
     * Setting buttong groups specificly is a more flexible alternative to the
     * buttons-array: you can directly control, which button goes in which group
     * and specify captions, hints and align-properties for every button group.
     * 
     * @uxon-property button_groups
     * @uxon-type \exface\Core\Widgets\ButtonGroup[]
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
    public function addButtonGroup(ButtonGroup $widget, $index = null)
    {
        if ($widget->getParent() !== $this){
            $widget->setParent($this);
        }
        
        if (is_null($index) || ! is_numeric($index)) {
            $this->button_groups[] = $widget;
        } else {
            array_splice($this->button_groups, $index, 0, array(
                $widget
            ));
        }
        
        return $this;
    }
    
    public function getButtonGroupIndex(ButtonGroup $button_group){
        // Make sure to search in the result of getButtonGroups() as extending
        // classes might change it's output.
        return array_search($button_group, $this->getButtonGroups());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainButtonGroups::getButtonGroup()
     */
    public function getButtonGroup($index)
    {
        if (!is_int($index)){
            return null;
        }
        // Make sure to search in the result of getButtonGroups() as extending
        // classes might change it's output.
        return $this->getButtonGroups()[$index];
    }
    
    /**
     * 
     * @param ButtonGroup $button_group
     * @return \exface\Core\Widgets\Toolbar
     */
    public function removeButtonGroup(ButtonGroup $button_group)
    {
        $key = array_search($button_group, $this->button_groups);
        if ($key !== false){
            unset($this->button_groups[$key]);
            // Reindex the array to avoid index gaps
            $this->button_groups = array_values($this->button_groups);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainButtonGroups::getButtonGroupFirst()
     */
    public function getButtonGroupFirst($alignment = null)
    {
        $found_grp = false;
        foreach ($this->getButtonGroups() as $grp){
            if (is_null($alignment) || $alignment == $grp->getAlign()){
                $found_grp = true;
                break;
            }
        }
        if (!$found_grp){
            $grp = $this->createButtonGroup();
            if (!is_null($alignment)){
                $grp->setAlign($alignment);
            }
            $this->addButtonGroup($grp);
        }
        return $grp;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\ButtonGroup::addButton()
     */
    public function addButton(Button $button_widget, $index = null)
    {
        $this->getButtonGroupFirst($button_widget->getAlign())->addButton($button_widget, $index);
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
     * Specifies the buttons in the toolbar via simple array.
     * 
     * The buttons will be automatically added to the first buttong group
     * matching the align-property of the button. Thus, the buttons will be
     * grouped by their align-property and placed left or right within the
     * toolbar according to it.
     * 
     * A more flexible alternative to the buttons array is the explicit
     * definition of buttong roups via the button_groups-property.
     * 
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\ButtonGroup::setButtons()
     */
    public function setButtons(array $buttons)
    {
        $this->getButtonGroupFirst()->setButtons($buttons);
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
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::countButtons()
     */
    public function countButtons()
    {
        $cnt = 0;
        foreach ($this->getButtonGroups() as $grp){
            $cnt += $grp->countButtons();
        }
        return $cnt;
    }
    
    /**
     * Places the toolbar at a specific position within the widget.
     * 
     * Which positions are possible, depends on the widget and on the template
     * used. As a rule of thumb, most widgets will support "top", "bottom" and 
     * "menu".
     * 
     * @uxon-property position
     * @uxon-type
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
    
    public function getButtonWidgetType()
    {
        return 'Button';
    }
}
?>