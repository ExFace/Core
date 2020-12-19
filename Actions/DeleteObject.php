<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iDeleteData;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;

/**
 * Deletes objects in the input data from their data sources.
 * 
 * @author Andrej Kabachnik
 *
 */
class DeleteObject extends AbstractAction implements iDeleteData
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
        $this->setIcon(Icons::TRASH_O);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $input_data = $this->getInputDataSheet($task);
        
        $deletedRows = $input_data->dataDelete($transaction);
        
        $result = ResultFactory::createMessageResult($task, $this->translate('RESULT', ['%number%' => $deletedRows], $deletedRows));
        
        if ($deletedRows > 0) {
            $result->setDataModified(true);
        }
        
        return $result;
    }
}
?>