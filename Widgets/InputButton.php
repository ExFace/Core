<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyNotSetError;

/**
 * InputCombo is similar to InputSelect extended by an autosuggest, that supports lazy loading.
 * It also can optionally accept new values.
 * 
 * @see InputSelect
 *
 * @author Andrej Kabachnik
 */
class InputButton extends Input
{
    private $buttonUxon = null;
    
    private $button = null;
    
    private $buttonPressOnStart = false;
    
    /**
     * 
     * @uxon-property button
     * @uxon-type \exface\Core\Widgets\Button
     * @uxon-template {"action":{"alias": ""}}
     *  
     * @param UxonObject $uxon
     * @return InputButton
     */
    public function setButton(UxonObject $uxon) : InputButton
    {
        $this->buttonUxon = $uxon;
        return $this;
    }
    
    /**
     * 
     * @throws WidgetPropertyNotSetError
     * @return Button
     */
    public function getButton() : Button
    {
        if ($this->button === null) {
            if ($this->buttonUxon !== null) {
                $this->button = WidgetFactory::createFromUxonInParent($this, $this->buttonUxon, 'Button');
            } else {
                throw new WidgetPropertyNotSetError($this, 'No button defined in ' . $this->getWidgetType() . ': please add the "button" property!');
            }
        }
        return $this->button;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren() : \Iterator
    {
        yield $this->getButton();
    }
    
    public function getButtonPressOnStart() : bool
    {
        return $this->buttonPressOnStart;
    }
    
    /**
     * Set to TRUE to perform the button's action automatically, when the widget is loaded.
     * 
     * @uxon-property button_press_on_start
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return InputButton
     */
    public function setButtonPressOnStart(bool $trueOrFalse) : InputButton
    {
        $this->buttonPressOnStart = $trueOrFalse;
        return $this;
    }
}