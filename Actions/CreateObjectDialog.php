<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;

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