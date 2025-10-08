<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;

/**
 * Performs calculations on the input data 
 * 
 * Calculations can be defined in different ways:
 * 
 * - Use `input_mapper` or `output_mapper`
 * - TODO
 * 
 * @author Andrej Kabachnik
 *
 */
class CalculateData extends AbstractAction implements iReadData
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $data_sheet = $this->getInputDataSheet($task);
        
        $result = ResultFactory::createDataResult($task, $data_sheet);
        if (null !== $message = $this->getResultMessageText()) {
            $message =  str_replace('%number%', $data_sheet->countRows(), $message);
        } else {
            $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.CALCULATEDATA.RESULT', ['%number%' => $data_sheet->countRows()], $data_sheet->countRows());
        }
        $result->setMessage($message);
        
        return $result;
    }
}