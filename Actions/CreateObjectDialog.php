<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;

class CreateObjectDialog extends EditObjectDialog
{

    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(null);
        $this->setInputRowsMax(null);
        $this->setIconName(Icons::PLUS);
        $this->setSaveActionAlias('exface.Core.CreateData');
        // Do not prefill with input data because we will be creating a new object in any case - regardless of the input data.
        // We can still make prefills setting widget values directly in UXON. Automatic prefills from the context can also be used.
        $this->setPrefillWithInputData(false);
        // We do want to get prefills from context, however.
        $this->setPrefillWithFilterContext(true);
    }

    protected function perform()
    {
        $this->prefillWidget();
        $this->setResultDataSheet($this->getInputDataSheet());
        $this->setResult($this->getWidget());
    }
}
?>