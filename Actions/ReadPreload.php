<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Widgets\iCanPreloadData;
use exface\Core\Exceptions\Actions\ActionInputTypeError;

/**
 * Exports the preload data sheet for the target widget.
 * 
 * @author Andrej Kabachnik
 *
 */
class ReadPreload extends ReadData
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
        
        if ($targetWidget = $this->getWidgetToReadFor($task)) {
            if ($targetWidget instanceof iCanPreloadData) {
                $data_sheet = $targetWidget->prepareDataSheetToPreload($data_sheet);
            } else {
                throw new ActionInputTypeError($this, 'Action ' . $this->getAliasWithNamespace() . ' not applicable to widget ' . $targetWidget->getWidgetType() . ': it can only be called for widgets, that support data preloading by implementing the interface iCanPreloadData.');
            }
        }
        
        $affected_rows = $data_sheet->dataRead();
        $result = ResultFactory::createDataResult($task, $data_sheet);
        $result->setMessage($affected_rows . ' entries read');
        
        return $result;
    }
}