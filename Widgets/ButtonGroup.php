<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iCanBeAligned;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Widgets\Traits\iUseInputWidgetTrait;
use exface\Core\Interfaces\Widgets\iContainButtonGroups;

/**
 * A group of button widgets visually separated from the other buttons.
 *
 * Button groups are mostly used within toolbars and menus to create visual
 * boundaries around a set of buttons: in a menu there would be separators
 * around a button group, while in a toolbar a buttong group might have extra
 * space around it.
 * 
 * Button groups can be aligned within a toolbar. If you have a wide toolbar,
 * you can put some button groups to the left and others to the right.
 * 
 * @method iContainButtonGroups getParent()
 *
 * @author Andrej Kabachnik
 *        
 */
class ButtonGroup extends Container implements iHaveButtons, iCanBeAligned, iUseInputWidget
{
    use iCanBeAlignedTrait {
        getAlign as getAlignDefault;
    }
    
    use iUseInputWidgetTrait;
    
    private $buttons = array();
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtons()
     */
    public function getButtons()
    {
        return $this->buttons;
    }
    
    /**
     * 
     * @param integer $min_visibility
     * @param integer $max_visibility
     */
    public function getButtonsByVisibility($min_visibility = EXF_WIDGET_VISIBILITY_OPTIONAL, $max_visibility = EXF_WIDGET_VISIBILITY_PROMOTED)
    {
        $btns = [];
        foreach ($this->getButtons() as $button){
            if ($button->getVisibility() >= $min_visibility && $button->getVisibility() <= $max_visibility){
                $btns[] = $button;
            }
        }
        return $btns;
    }

    /**
     * Defines the contained buttons via array of button definitions.
     *
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::setButtons()
     */
    public function setButtons(array $buttons_array)
    {
        foreach ($buttons_array as $b) {
            if ($b instanceof Button){
                $button = $b;
            } elseif ($b instanceof UxonObject){
                // If the widget type of the Button is explicitly defined, use it, otherwise fall back to the button widget type of
                // this widget: i.e. Button for simple Forms, DialogButton for Dialogs, etc.
                $button_widget_type = $b->hasProperty('widget_type') ? $b->getProperty('widget_type') : $this->getButtonWidgetType();
                $button = $this->getPage()->createWidget($button_widget_type, $this, UxonObject::fromAnything($b));
            } else {
                throw new WidgetPropertyInvalidValueError($this, 'Cannot use "' . gettype($b) . '" as button in ' . $this->getWidgetType() . '": instantiated button widget (or derivative) or corresponding UXON object expected!');
            }
            // Add the button to the group
            $this->addButton($button);
        }
        return $this;
    }

    /**
     * Adds a button to the group
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::addButton()
     */
    public function addButton(Button $button_widget, $index = null)
    {
        if ($button_widget->getParent() !== $this){
            $button_widget->setParent($this);
        }
        
        if (is_null($index) || ! is_numeric($index)) {
            $this->buttons[] = $button_widget;
        } else {
            array_splice($this->buttons, $index, 0, array(
                $button_widget
            ));
        }
        
        return $this;
    }

    /**
     * Removes a button from the group
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::removeButton()
     */
    public function removeButton(Button $button_widget)
    {
        if (($key = array_search($button_widget, $this->buttons)) !== false) {
            unset($this->buttons[$key]);
            // Reindex the buttons array to avoid index gaps
            $this->buttons = array_values($this->buttons);
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren()
    {
        return array_merge(parent::getChildren(), $this->getButtons());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        if ($this->getParent() instanceof Toolbar){
            return $this->getParent()->getButtonWidgetType();
        } elseif (method_exists($this->getInputWidget(), 'getButtonWidgetType')){
            return $this->getInputWidget()->getButtonWidgetType();
        }
        return 'Button';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::hasButtons()
     */
    public function hasButtons()
    {
        if (count($this->buttons))
            return true;
        else
            return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonIndex()
     */
    public function getButtonIndex(Button $widget)
    {
        return array_search($widget, $this->buttons);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButton()
     */
    public function getButton($index)
    {
        if (!is_int($index)){
            return null;
        }
        return $this->buttons[$index];
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Button::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        
        $buttons = array();
        foreach ($this->getButtons() as $button) {
            $buttons[] = $button->exportUxonObject();
        }
        $uxon->setProperty('buttons', $buttons);
        
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::countButtons()
     */
    public function countButtons()
    {
        return count($this->getButtons());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::countButtonsVisible()
     */
    public function countButtonsVisible()
    {
        $cnt = 0;
        foreach ($this->getButtons() as $btn){
            if (!$btn->isHidden()){
                $cnt++;
            }
        }
        return $cnt;
    }
    
    public function getAlign()
    {
        if (is_null($this->getAlignDefault())){
            foreach ($this->getButtons() as $btn){
                if ($btn->getAlign()){
                    $this->setAlign($btn->getAlign());
                }
                break;
            }
        }
        return $this->getAlignDefault();
    }
}
?>