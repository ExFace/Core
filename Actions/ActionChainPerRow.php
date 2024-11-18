<?php
namespace exface\Core\Actions;

use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Actions\iCallOtherActions;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Tasks\ResultDataInterface;

/**
 * This action is working like a normal action chain, but it performs the actions for each row seperately.
 * 
 * Means all actionsare performed for a single input data entry seperately and then again for the next entry.
 * 
 * @author Andrej Kabachnik
 *        
 */
class ActionChainPerRow extends ActionChain implements iCallOtherActions
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        if (empty($this->getActions())) {
            throw new ActionConfigurationError($this, 'An action chain must contain at least one action!', '6U5TRGK');
        }        
        $t = $task->copy();
        $inputSheet = $this->getInputDataSheet($task);
        $t = $task;
        $singleResults = [];
        $singleSheet = $inputSheet->copy();
        foreach ($inputSheet->getRows() as $idx => $row) {
            $singleSheet = $singleSheet->removeRows()->addRow($row);
            $tSingle = $t->copy()->setInputData($singleSheet);
            $singleResults[$idx] = parent::perform($tSingle, $transaction);
        }
        if (count($singleResults) === 0) {
            return ResultFactory::createEmptyResult($task);
        }
        $combinedResult = null;
        if ($singleResults[0] instanceof ResultDataInterface) {
            foreach ($singleResults as $result) {
                if ($combinedResult === null) {
                    $combinedResult = $result;
                    continue;
                }
                $combinedResult->getData()->addRows($result->getData()->getRows());
            }
        } else {
            $combinedResult = $singleResults[array_key_last($singleResults)];
        }
        $resultCount = count($singleResults);
        // TODO maybe enhance output to show output of every signle result for debbuging purposes
        $combinedResult->setMessage("Action chain performed for {$resultCount} rows seperately.");
        return $combinedResult;        
    }
}