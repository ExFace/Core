<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iAmClosable;

/**
 * A special type of button to use in dialogs.
 * 
 * Additionally to the normal button functionality this button can explicitly control 
 * the dialog it belongs to. Thus, the user can decide whether the dialog is to be closed 
 * after the button's action is performed or not.
 * 
 * 
 *  ## Refreshing data after an action
 * 
 *  After an action succeeds, the button tells the UI what might have changed. Containers (e.g. dialogs) and data
 *  widgets (tables, charts, etc.) listen to this and reload themselves if they are affected. In most cases this
 *  works automatically: an action that modifies data (e.g. `SaveData`) knows which objects it effects.
 * 
 *  Actions that do not modify data themselves - e.g. `ShowDialog` - have no automatic effects. If such a button
 *  opens a dialog, in which data is changed, the widgets around the button will not notice this. A typical example
 *  is a "Comment" button in a read-only dialog of a `REPORT`: the comment is saved in the opened dialog, but the
 *  comment history shown in the report dialog stays outdated.
 * 
 *  To fix this, add an effect on the object of the widgets that need to reload to the action of the button:
 * 
 *  ```
 *   {
 *       "widget_type": "DialogButton",
 *       "close_dialog": false,
 *       "action": {
 *           "alias": "my.App.CommentReport",
 *           "effects": [
 *               {"name": "Refresh report", "effected_object": "my.App.REPORT"}
 *           ]
 *       }
 *   }
 * 
 *  ```
 * 
 *  What happens then:
 * 
 *  1. The user saves the comment in the dialog opened by the button. This dialog closes.
 *  2. When a dialog opened by a button closes, the effects of that button's action are published.
 *  3. Every container and data widget based on `my.App.REPORT` reloads its data - e.g. the report dialog
 *  re-reads its prefill, which updates all values shown in it, including the comment history.
 * 
 *  Things to keep in mind:
 * 
 *  - Set `effected_object` to the object of the **container** that needs to reload (e.g. the dialog), not to
 *  the object of a single value widget. Do not add a `relation_path_to_effected_object` - effects with relation
 *  paths are treated as secondary and do not trigger container refreshes.
 *  - A container with unsaved changes will not be refreshed by such an effect to prevent data loss. This is
 *  no problem for read-only dialogs.
 *  - The entire container reloads, not just a single widget. Every other container or data widget on the page
 *  that is based on the same object will reload too.
 *  - `refresh_widget_ids` has no effect on simple value widgets like `Display` or `DisplayMarkdown`: they cannot
 *  reload themselves, but get their values from their container. Use an effect as shown above instead.
 *  - For a simple list of objects without names, use `effected_objects` in the action instead of `effects`.
 * 
 * @author Andrej Kabachnik
 *        
 */
class DialogButton extends Button
{

    private $close_dialog_after_action_succeeds = true;

    private $close_dialog_after_action_fails = false;

    /**
     * 
     * @return bool
     */
    public function getCloseDialogAfterActionSucceeds() : bool
    {
        return $this->close_dialog_after_action_succeeds;
    }

    /**
     * Makes the button close the dialog once it's action is performed successfully - TRUE by default.
     * 
     * @uxon-property close_dialog_after_action_succeeds
     * @uxon-type boolean 
     * @uxon-default true
     * 
     * @param bool $value
     * @return DialogButton
     */
    public function setCloseDialogAfterActionSucceeds(bool $value) : DialogButton
    {
        $this->close_dialog_after_action_succeeds = $value;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getCloseDialogAfterActionFails() : bool
    {
        return $this->close_dialog_after_action_fails;
    }

    /**
     * Makes the button close the dialog if it's action causes an error (before showing the error) - FALSE by default.
     * 
     * @uxon-property close_dialog_after_action_fails
     * @uxon-type boolean 
     * @uxon-default false
     * 
     * @param bool|string $value
     * @return DialogButton
     */
    public function setCloseDialogAfterActionFails($value) : DialogButton
    {
        $this->close_dialog_after_action_fails = BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     * Makes the button close (TRUE) or leave open (FALSE) the dialog after it had been pressed - regardless of the result.
     *
     * @uxon-property close_dialog
     * @uxon-type boolean
     *
     * @param bool|string $value
     * @return DialogButton
     */
    public function setCloseDialog($value) : DialogButton
    {
        $this->setCloseDialogAfterActionFails($value);
        $this->setCloseDialogAfterActionSucceeds($value);
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
        $uxon->setProperty('close_dialog_after_action_succeeds', $this->getCloseDialogAfterActionSucceeds());
        $uxon->setProperty('close_dialog_after_action_fails', $this->getCloseDialogAfterActionFails());
        return $uxon;
    }
    
    /**
     * Returns the Dialog, this button belongs to.
     * 
     * In constrast to getInputWidget(), this method allways returns the containing dialog - even 
     * if the button has a custom input widget.
     * 
     * @throws WidgetConfigurationError
     * @return iAmClosable
     */
    public function getDialog() : iAmClosable
    {
        $input = $this->getInputWidget();
        if ($input instanceof iAmClosable) {
            return $input;
        } elseif ($dialog = $this->getParentByClass(iAmClosable::class)) {
            return $dialog;
        }
        
        throw new WidgetConfigurationError($this, 'Widget DialogButton can only be placed inside a Dialog widget!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Button::getRefreshInput()
     */
    public function getRefreshInput() : ?bool
    {
        if ($this->getCloseDialogAfterActionSucceeds() === true && $this->getInputWidget() instanceof Dialog) {
            return false;
        }
        return parent::getRefreshInput();
    }
}