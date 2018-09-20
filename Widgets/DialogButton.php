<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * A special type of button to use in dialogs.
 * Additionally to the normal button functionality
 * this button can explicitly control the dialog it belongs to. Thus, the user can decide whether
 * the dialog is to be closed after the button's action is performed or not.
 *
 * @author PATRIOT
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
     * 
     * @param bool|int|string $value
     * @return DialogButton
     */
    public function setCloseDialogAfterActionSucceeds($value) : DialogButton
    {
        $this->close_dialog_after_action_succeeds = BooleanDataType::cast($value);
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
     * 
     * @param bool|int|string $value
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
     * @uxon-property close_dialog_after_action_succeeds
     * @uxon-type boolean
     *
     * @param bool|int|string $value
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
     * @return Dialog
     */
    public function getDialog() : Dialog
    {
        $input = $this->getInputWidget();
        if ($input instanceof Dialog) {
            return $input;
        } else {
            $parent = $this->getParent();
            while (! ($parent instanceof Dialog)) {
                if (! $parent->hasParent()) {
                    throw new WidgetConfigurationError($this, 'Widget DialogButton can only be placed inside a Dialog widget!');
                }
                $parent = $parent->getParent();
            }
            return $parent;
        }
    }
}
?>