<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Widgets\Button;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

interface iHaveButtons extends iHaveChildren
{

    /**
     * Adds a button to the widget
     *
     * @param \exface\Core\Widgets\Button $button_widget            
     */
    public function addButton(Button $button_widget);

    /**
     * Removes a button from the widget
     *
     * @param Button $button_widget            
     */
    public function removeButton(Button $button_widget);

    /**
     * Returs an array of button widgets
     *
     * @return Button[]
     */
    public function getButtons();

    /**
     * Adds multiple buttons from an array of their UXON descriptions
     *
     * @throws WidgetPropertyInvalidValueError if the array includes miscofigured widgets or widget, that are not buttons.
     * @param Button[]|UxonObject[] $buttons
     * @return iHaveButtons
     */
    public function setButtons(array $buttons);

    /**
     *
     * @return boolean
     */
    public function hasButtons();

    /**
     * Returns the widget type to be used for buttons in this widget.
     * Regular forms use ordinary buttons, but Dialogs
     * use special DialogButtons capable of closing the Dialog, Data widgets use DataButtons, that can be bound to
     * mouse clicks on the data, etc. This special getter function allows all the logic to be inherited while just
     * replacing the button class.
     *
     * @return string
     */
    public function getButtonWidgetType();
    
    /**
     * Returns the number of buttons in the group
     *
     * @return number
     */
    //public function countButtons();
    
    /**
     * Returns the number of buttons in the group, that are not hidden
     *
     * @return number
     */
    //public function countButtonsVisible();
}