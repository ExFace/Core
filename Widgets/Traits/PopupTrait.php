<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Actions\iShowDialog;
use exface\Core\Interfaces\Actions\iCallOtherActions;
use exface\Core\Interfaces\Widgets\iAmClosable;
use exface\Core\Widgets\DialogButton;
use exface\Core\Widgets\Button;

/**
 * 
 * 
 * @author Andrej Kabachnik
 */
trait PopupTrait
{
    private $hide_close_button = false;
    
    private $close_button = null;
    
    private $close_button_action = null;
    
    /**
     * Returns the button that opened the dialog or NULL if not available
     *
     * @return iTriggerAction|NULL
     */
    public function getOpenButton() : ?iTriggerAction
    {
        if ($this->hasParent()) {
            $parent = $this->getParent();
            if ($parent instanceof iTriggerAction) {
                return $parent;
            }
        }
        return null;
    }
    
    /**
     * Returns the action that opened the dialog or NULL if not available
     *
     * @return iShowDialog|NULL
     */
    public function getOpenAction() : ?iShowDialog
    {
        if ($button = $this->getOpenButton()) {
            if ($button->hasAction()) {
                $action = $button->getAction();
                switch (true) {
                    // If the action shows a dialog, that's it
                    case $action instanceof iShowDialog:
                        return $action;
                        // If the action calls other actions, see if one of them fits: that is, it shows exactly
                        // our dialog. It is importantch to check for the dialog id as CallAction action might
                        // have multiple actions calling each their own dialog!
                    case $action instanceof iCallOtherActions:
                            $thisDialogId = $this->getId();
                            foreach ($action->getActionsRecursive() as $innerAction) {
                                if (($innerAction instanceof iShowDialog) && $innerAction->getDialogWidget()->getId() === $thisDialogId) {
                                    return $innerAction;
                                }
                            }
                            break;
                }
            }
        }
        return null;
    }
    
    /**
     * If TRUE, the automatically generated close button for the dialog is not shown
     *
     * @return boolean
     */
    public function getHideCloseButton()
    {
        return $this->hide_close_button;
    }
    
    /**
     * If set to TRUE, the automatically generated close button will not be shown in this dialog
     *
     * @uxon-property hide_close_button
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param boolean $value
     * @return iAmClosable
     */
    public function setHideCloseButton($value)
    {
        $this->hide_close_button = $value;
        if ($this->close_button !== null) {
            $this->close_button->setHidden($value);
        }
        return $this;
    }
    
    /**
     * Returns a special dialog button, that just closes the dialog without doing any other action
     *
     * @return \exface\Core\Widgets\DialogButton
     */
    public function getCloseButton()
    {
        if (! ($this->close_button instanceof DialogButton)) {
            $this->close_button = $this->createCloseButton();
        }
        return $this->close_button;
    }
    
    protected function createCloseButton() : Button
    {
        $btn = $this->createButton();
        if ($btn instanceof DialogButton) {
            $btn->setCloseDialogAfterActionSucceeds(true);
        }
        $btn->setRefreshInput(false);
        $btn->setShowIcon(false);
        $btn->setCaption($this->translate('WIDGET.POPUP.CLOSE_BUTTON_CAPTION'));
        $btn->setAlign(EXF_ALIGN_OPPOSITE);
        if ($this->getHideCloseButton()) {
            $btn->setHidden(true);
        }
        if ($this->hasCloseButtonAction()) {
            $btn->setAction($this->getCloseButtonActionUxon());
        }
        return $btn;
    }
    
    /**
     * Sets the action, that the close button will trigger.
     *
     * @uxon-property close_button_action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": ""}
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iDefineAction::setAction()
     * @param UxonObject $uxon
     * @return iAmClosable
     */
    public function setCloseButtonAction(UxonObject $uxon) : iAmClosable
    {
        $this->close_button_action = $uxon;
        if ($this->close_button !== null) {
            $this->close_button->setAction($uxon);
        }
        return $this;
    }
    
    /**
     * Returns NULL or the UXON for the close button action
     *
     * @return UxonObject|NULL
     */
    protected function getCloseButtonActionUxon() : ?UxonObject
    {
        return $this->close_button_action;
    }
    
    /**
     * Returns true if the close button has an action uxon
     *
     * @return boolean
     */
    public function hasCloseButtonAction() : bool
    {
        return $this->close_button_action !== null;
    }
}