<?php

namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractActionShowDynamicDialog;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Widgets\Dialog;

class ShowShareLink extends ShowDialog
{
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::SHARE);
        $this->setInputRowsMax(1);
        $this->setPrefillWithInputData(true);
        $this->setPrefillWithPrefillData(true);
        $this->setPrefillWithFilterContext(false);
    }

    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        $pageAlias = $this->getPage()->getAliasWithNamespace();
        $widgetId = $this->getWidget()->getParent();
        return parent::perform($task, $transaction);
    }

}