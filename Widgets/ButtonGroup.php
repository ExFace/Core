<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\CommonLogic\UxonObject;

/**
 * A group of button widgets with a mutual input widget.
 *
 * Depending on the template, a ButtonGroup can be displayed as a list of buttons or even transformed to a menu.
 *
 * @author Andrej Kabachnik
 *        
 */
class ButtonGroup extends Button implements iHaveButtons
{

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
     * Defines the contained buttons via array of button definitions.
     *
     * @uxon-property buttons
     * @uxon-type Button[]
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::setButtons()
     */
    public function setButtons(array $buttons_array)
    {
        if (! is_array($buttons_array))
            return false;
        foreach ($buttons_array as $b) {
            $button = $this->getPage()->createWidget('Button', $this, UxonObject::fromAnything($b));
            $this->addButton($button);
        }
    }

    /**
     * Adds a button to the group
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::addButton()
     */
    public function addButton(Button $button_widget)
    {
        $button_widget->setParent($this);
        if ($this->getInputWidget()){
            $button_widget->setInputWidget($this->getInputWidget());
        }
        $this->buttons[] = $button_widget;
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
}
?>