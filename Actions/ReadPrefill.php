<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class ReadPrefill extends ReadData
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ReadData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        if (! $this->checkPermissions($task)) {
            // TODO Throw exception!
        }
        
        $data_sheet = $this->getInputDataSheet($task);
        
        if ($data_sheet->isEmpty()) {
            return $data_sheet;
        } else {
            if ($data_sheet->hasUidColumn(true)) {
                $data_sheet->addFilterFromColumnValues($data_sheet->getUidColumn());
            } else {
                return $data_sheet->removeRows();
            }
        }
        
        if ($task->isTriggeredByWidget()) {
            $data_sheet = $task->getWidgetTriggeredBy()->prepareDataSheetToRead($data_sheet);
        }
        $affected_rows = $data_sheet->dataRead();
        
        $result = ResultFactory::createDataResult($task, $data_sheet);
        $result->setMessage($affected_rows . ' entries read');
        
        return $result;
    }
}