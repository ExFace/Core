<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;

/**
 * Displays a dialog to edit the meta object of the action.
 * 
 * If no dialog is explicitly defined within the action, it will automatically use the
 * default editor of the object from it's metamodel. If there is no default editor, the
 * action will attempt to create a simple editor dialog like the EditObjectDialog action
 * would do.
 * 
 * @author Andrej Kabachnik
 *
 */
class CreateObjectDialog extends EditObjectDialog
{

    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(null);
        $this->setInputRowsMax(null);
        $this->setIcon(Icons::PLUS);
        $this->setSaveActionAlias('exface.Core.CreateData');
        // Do not prefill with input data because we will be creating a new object in any case - regardless of the input data.
        // We can still make prefills setting widget values directly in UXON. Automatic prefills from the context can also be used.
        $this->setPrefillWithInputData(false);
        // We do want to get prefills from context, however.
        $this->setPrefillWithFilterContext(true);
    }
}
?>