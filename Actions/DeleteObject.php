<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\Interfaces\Actions\iDeleteData;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\Exceptions\Actions\ActionRuntimeError;

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
        
        if ($this->getConfirmations()->isPossible()) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            $this->getConfirmations()->addFromUxon(new UxonObject([
                'widget_type' => 'ConfirmationMessage',
                'type' => MessageTypeDataType::WARNING,
                'caption' => $translator->translate('ACTION.DELETEOBJECT.CONFIRMATION_TITLE'),
                'text' => $translator->translate('ACTION.DELETEOBJECT.CONFIRMATION_TEXT')
            ]));
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $input_data = $this->getInputDataSheet($task);
        
        // IDEA delete action objects via filter if input data includes relations to them (not tested yet!)
        /*
        if (! $input_data->getMetaObject()->is($this->getMetaObject()) && $input_data->hasUidColumn(true)) {
            foreach ($input_data->getColumns() as $col) {
                if ($col->isAttribute() && $col->getAttribute()->getObject()->isExactly($this->getMetaObject())) {
                    $relPathFromInput = $col->getAttribute()->getRelationPath();
                    $relPathToInput = $relPathFromInput->reverse();
                    $dataToDelete = DataSheetFactory::createFromObject($this->getMetaObject());
                    $dataToDelete->getFilters()->addConditionFromString($relPathToInput->toString(), $col->getValues(false), ComparatorDataType::IN);
                }
            }
        }*/
        
        // If the input data does not contain the object to delete - error
        if (! $input_data->getMetaObject()->is($this->getMetaObject())) {
            throw new ActionInputInvalidObjectError($this, 'Cannot delete object ' . $this->getMetaObject()->__toString() . ' using input data of object ' . $input_data->getMetaObject()->__toString() . '!');
        }
        
        try {
            $deletedRows = $input_data->dataDelete($transaction);
        } catch (\Throwable $e) {
            throw new ActionRuntimeError($this, 'Cannot delete data of object ' . $this->getMetaObject()->__toString() . '. ' . $e->getMessage(), null, $e);
        }
        
        
        if (null !== $message = $this->getResultMessageText()) {
            $message =  str_replace('%number%', $deletedRows, $message);
        } else {
            $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.DELETEOBJECT.RESULT', ['%number%' => $deletedRows], $deletedRows);
        }
        $result = ResultFactory::createMessageResult($task, $message);
        
        if ($deletedRows > 0) {
            $result->setDataModified(true);
        }
        
        return $result;
    }
}
?>