<?php
namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

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
class DataButton extends Button
{
    const INPUT_ROWS_ALL = 'all';
    const INPUT_ROWS_ALL_AS_SUBSHEET = 'all_as_subsheet';
    const INPUT_ROWS_SELECTED = 'selected';
    const INPUT_ROWS_AUTO = 'auto';
    const INPUT_ROWS_NONE = 'none';
    
    private $bind_to_mouse_action = null;
    
    private $bind_to_single_result = false;
    
    private $inputRows = null;

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
     * Set to TRUE to perform the action of this button when an item of the parent Data widget is doubleclicked
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
    
    /**
     * 
     * @return string|NULL
     */
    public function getInputRows() : ?string
    {
        return $this->inputRows;
    }
    
    /**
     * Specify, what rows of the input widget to pass the action of this button: all, selected or only those changed.
     * 
     * By default this is determined automatically based on the action to be performed. However, on rare
     * occasions the option needs to be overridden manually: e.g. if a CallWebService action is actually
     * modifying data, it may need all the rows instead of the selected ones.
     * 
     * @uxon-property input_rows
     * @uxon-type [auto,all,all_as_subsheet,selected,none]
     * @uxon-default auto
     *
     * @param string $value
     * @return Button
     */
    public function setInputRows(string $value) : Button
    {
        if (! defined('self::INPUT_ROWS_' . strtoupper($value))) {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid value "' . $value . '" for `input_rows` of widget "' . $this->getWidgetType() . '"!');
        }
        $this->inputRows = strtolower($value);
        return $this;
    }
}