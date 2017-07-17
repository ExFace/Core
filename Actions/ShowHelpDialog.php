<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\Dialog;
use exface\Core\Exceptions\Actions\ActionCallingWidgetNotSpecifiedError;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Widgets\iHaveContextualHelp;
use exface\Core\Exceptions\Actions\ActionLogicError;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * This action opens a dialog with the auto-generated contextual help for the input widget of it's caller.
 *
 * You can add a button calling this action to any widget that implements iHaveContextualHelp and it will open
 * a help-dialog for that widget. The contextual help is generated automatically from object and attribute
 * descriptions in the meta model. It will no contain anything if these descriptions are empty. *
 *
 * @author Andrej Kabachnik
 *        
 */
class ShowHelpDialog extends ShowDialog
{

    protected function init()
    {
        parent::init();
        $this->setIconName(Icons::QUESTION_CIRCLE_O);
        $this->setPrefillWithFilterContext(false);
        $this->setPrefillWithInputData(false);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Actions\ShowDialog::enhanceDialogWidget()
     */
    protected function enhanceDialogWidget(Dialog $dialog)
    {
        $dialog = parent::enhanceDialogWidget($dialog);
        
        // IMPORTANT: remove help button from the help dialog to prevent infinite help popups
        $dialog->setHideHelpButton(true);
        
        if ($this->getCalledByWidget() && $this->getCalledByWidget() instanceof iTriggerAction) {
            if ($this->getCalledByWidget()->getInputWidget() instanceof iHaveContextualHelp) {
                $this->getCalledByWidget()->getInputWidget()->getHelpWidget($dialog);
            } else {
                throw new ActionLogicError($this, 'Calling widget cannot generate contextual help: id does not implement the interface iHaveContextualHelp!', '6V9XDV4');
            }
        } else {
            throw new ActionCallingWidgetNotSpecifiedError($this, 'No calling widget passed to action "' . $this->getAliasWithNamespace() . '"!', '6V9XDV4');
        }
        return $dialog;
    }
}
?>