<?php
namespace exface\Core\Widgets;

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

    public function getCloseDialogAfterActionSucceeds()
    {
        return $this->close_dialog_after_action_succeeds;
    }

    public function setCloseDialogAfterActionSucceeds($value)
    {
        $this->close_dialog_after_action_succeeds = $value;
    }

    public function getCloseDialogAfterActionFails()
    {
        return $this->close_dialog_after_action_fails;
    }

    public function setCloseDialogAfterActionFails($value)
    {
        $this->close_dialog_after_action_fails = $value;
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
}
?>