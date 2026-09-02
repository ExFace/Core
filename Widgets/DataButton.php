<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSpecifyInputRows;
use exface\Core\Widgets\Traits\iSpecifyInputRowsTrait;

/**
 * A special type of button to use in DataTables and other Data widgets.
 * The action can be bound to clicks on the Data widget.
 *
 * Additionally to the normal button functionality this button can be assigned as a click action. So, if a button is bound to
 * a double click, it's action will be called if the user doubleclicks a data element. In theory multiple buttons can be bound
 * to a click action - if so, the facade should show a popup menu for this action.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataButton extends Button implements iSpecifyInputRows
{
    use iSpecifyInputRowsTrait;
    
    private $bind_to_mouse_action = null;
    
    private $bind_to_single_result = false;

    /**
     * Returns the mouse action, this button is bound to (one of the EXF_MOUSE_ACTION_*** constants) or NULL if the button
     * is not bound to any mouse action.
     *
     * @return string
     */
    public function getBindToMouseAction()
    {
        return $this->bind_to_mouse_action;
    }

    /**
     * Binds the button to a specific mouse action (like a double click).
     * Accepts one of the EXF_MOUSE_ACTION_*** constants
     *
     * @param string $mouse_action_name            
     * @return DataButton
     */
    public function setBindToMouseAction($mouse_action_name)
    {
        $this->bind_to_mouse_action = $mouse_action_name;
        return $this;
    }

    /**
     * Set to TRUE to perform the action of this button when an item of the parent Data widget is double-clicked.
     * 
     * If multiple buttons get `bind_to_double_click`, the first one, that is active will be "pressed". This is
     * very useful, if you have different default actions depending on the state of a row - e.g. double-clicking
     * an order will edit it if it is a draft or show an info dialog if it has been released already.
     * 
     * This also works if the buttons are placed inside a `MenuButton`.
     *
     * @uxon-property bind_to_double_click
     * @uxon-type boolean
     *
     * This a shortcut for set_bind_to_mouse_action(EXF_MOUSE_ACTION_DOUBLE_CLICK), which makes it possible to use easy-to-
     * understand UXON-attributes
     *
     * @see set_bind_to_mouse_action()
     * @return DataButton
     */
    public function setBindToDoubleClick($value)
    {
        if ($value) {
            $this->setBindToMouseAction(EXF_MOUSE_ACTION_DOUBLE_CLICK);
        }
        return $this;
    }

    /**
     * Set to TRUE to perform the action of this button when an item of the parent Data widget is right clicked
     *
     * @uxon-property bind_to_right_click
     * @uxon-type boolean
     *
     * This a shortcut for set_bind_to_mouse_action(EXF_MOUSE_ACTION_RIGHT_CLICK), which makes it possible to use easy-to-
     * understand UXON-attributes
     *
     * @see set_bind_to_mouse_action()
     * @return DataButton
     */
    public function setBindToRightClick($value)
    {
        if ($value) {
            $this->setBindToMouseAction(EXF_MOUSE_ACTION_RIGHT_CLICK);
        }
        return $this;
    }

    /**
     * Set to TRUE to perform the action of this button when an item of the parent Data widget is left clicked
     *
     * @uxon-property bind_to_left_click
     * @uxon-type boolean
     *
     * This a shortcut for set_bind_to_mouse_action(EXF_MOUSE_ACTION_DOUBLE_CLICK), which makes it possible to use easy-to-
     * understand UXON-attributes
     *
     * @see set_bind_to_mouse_action()
     * @return DataButton
     */
    public function setBindToLeftClick($value)
    {
        if ($value) {
            $this->setBindToMouseAction(EXF_MOUSE_ACTION_LEFT_CLICK);
        }
        return $this;
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
        $uxon->setProperty('bind_to_mouse_action', $this->getBindToMouseAction());
        return $uxon;
    }
    
    /**
     *
     * @return bool
     */
    public function isBoundToSingleResult() : bool
    {
        return $this->bind_to_single_result;
    }
    
    /**
     * Automatically performs the button's action if the input data widget loads a single result.
     * 
     * @uxon-property bind_to_single_result
     * @uxon-type boolean
     * @uxon-default false 
     * 
     * @param bool $value
     * @return DataButton
     */
    public function setBindToSingleResult(bool $value) : DataButton
    {
        $this->bind_to_single_result = $value;
        return $this;
    }
}