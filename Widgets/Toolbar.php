<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iContainButtonGroups;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetChildNotFoundError;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Widgets\Traits\iUseInputWidgetTrait;
use exface\Core\Exceptions\UnderflowException;

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
class Toolbar extends Container implements iHaveButtons, iContainButtonGroups, iUseInputWidget
{
    const POSITION_DEFAULT = 'default';
    
    const POSITION_TOP = 'top';
    
    const POSITION_BOTTOM = 'bottom';
    
    const POSITION_MENU = 'menu';
    
    private $position = null;
    
    use iUseInputWidgetTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainButtonGroups::getButtonGroups()
     */
    public function getButtonGroups(callable $filter_callback = null)
    {
        return $this->getWidgets($filter_callback);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainButtonGroups::createButtonGroup()
     */
    public function createButtonGroup(UxonObject $uxon = null)
    {
        if (!is_null($uxon)){
            return WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'ButtonGroup');
        }
        return WidgetFactory::create($this->getPage(), 'ButtonGroup', $this);
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
                $this->addButtonGroup(WidgetFactory::createFromUxon($this->getPage(), $group, $this, 'ButtonGroup'));
            }
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainButtonGroups::addButtonGroup()
     */
    public function addButtonGroup(ButtonGroup $widget, $index = null)
    {        
        return $this->addWidget($widget, $index);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainButtonGroups::getButtonGroupIndex()
     */
    public function getButtonGroupIndex(ButtonGroup $button_group){
        return $this->getWidgetIndex($button_group);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainButtonGroups::getButtonGroup()
     */
    public function getButtonGroup($index)
    {
        return $this->getWidget($index);
    }
    
    /**
     * 
     * @param ButtonGroup $button_group
     * @return \exface\Core\Widgets\Toolbar
     */
    public function removeButtonGroup(ButtonGroup $button_group)
    {
        return $this->removeWidget($button_group);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainButtonGroups::getButtonGroupFirst()
     */
    public function getButtonGroupFirst($alignment = null)
    {        
        // If the underlying container is empty, add the first button group.
        if (! parent::hasWidgets()){
            $this->addButtonGroup($this->createButtonGroup());
        }
        
        try {
            if (is_null($alignment)){
                $filter = null;
            } else {
                $filter = function(ButtonGroup $grp) use ($alignment) {
                    return $grp->getAlign() === $alignment;
                };
            }
            $grp = $this->getWidgetFirst($filter);
        } catch (UnderflowException $e){
            $grp = $this->createButtonGroup();
            if ($alignment){
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
    public function getButtons(callable $filter_callback = null)
    {
        $buttons = [];
        foreach ($this->getButtonGroups() as $grp){
            $buttons = array_merge($buttons, $grp->getButtons($filter_callback));
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
        $btn_grps = [];
        foreach ($buttons as $btn){
            if ($btn instanceof UxonObject){
                $btn_grps[$btn->hasProperty('align') ? $btn->getProperty('align') : EXF_ALIGN_DEFAULT][] = $btn;
            } elseif ($btn instanceof Button){
                $btn_grps[$btn->getAlign()][] = $btn;
            } else {
                $btn_grps[EXF_ALIGN_DEFAULT] = $btn;
            }
        }
        
        foreach ($btn_grps as $align => $btns){
            $this->getButtonGroupFirst($align)->setButtons($btns);
        }
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
    public function countButtons(callable $filter_callback = null)
    {
        $cnt = 0;
        foreach ($this->getButtonGroups() as $grp){
            $cnt += $grp->countButtons($filter_callback);
        }
        return $cnt;
    }
    
    /**
     * Returns the position of this toolbar: values depend on the widget, that
     * contains the toolbar.
     * 
     * @return string
     */
    public function getPosition()
    {
        if (is_null($this->position)){
            $this->setPosition(static::POSITION_DEFAULT);
        }
        return $this->position;
    }
    
    /**
     * Places the toolbar at a specific position within the widget.
     * 
     * Which positions are possible, depends on the widget and on the template
     * used. As a rule of thumb, most widgets will support "top", "bottom" and 
     * "menu".
     * 
     * @uxon-property position
     * @uxon-type string
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::createButton()
     */
    public function createButton(UxonObject $uxon = null)
    {
        return $this->getButtonGroupFirst()->createButton($uxon);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonIndex()
     */
    public function getButtonIndex(Button $widget)
    {
        return array_search($widget, $this->getButtons());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButton()
     */
    public function getButton($index)
    {
        if (!is_int($index)){
            throw new \UnexpectedValueException('Invalid index "' . $index . '" used to search for a child widget!');
        }
        
        $widgets = $this->getButtons();
        
        if (! array_key_exists($index, $widgets)){
            throw new WidgetChildNotFoundError($this, 'No child widget found with index "' . $index . '" in ' . $this->getWidgetType() . '!');
        }
        
        return $widgets[$index];
    }

}
?>