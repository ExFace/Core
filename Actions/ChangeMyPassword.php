<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\Security\AuthenticationToken\UsernamePasswordAuthToken;
use exface\Core\Exceptions\Actions\ActionChangeMyPasswordFailedError;

class ChangeMyPassword extends UpdateData
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\SaveData::init()
     */
    protected function init()
    {
        $this->setIcon(Icons::LOCK);
        $this->setInputRowsMin(0);
        $this->setInputRowsMax(1);
        $this->setInputObjectAlias('exface.Core.USER');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\UpdateData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $dataSheet = $this->getInputDataSheet($task);
        
        if (! $dataSheet->getColumns()->getByExpression('USERNAME') || ! $dataSheet->getColumns()->getByExpression('PASSWORD') || ! $dataSheet->getColumns()->getByExpression('OLD_PASSWORD')) {
            throw new DataSheetColumnNotFoundError($dataSheet, "Can not update password, make sure the data sheet contains the Columns 'USER', 'PASSWORD' and 'OLD_PASSWORD'!");
        }
        $user = $dataSheet->getRow(0)['USERNAME'];
        if ($user !== $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUsername()) {
            throw new ActionChangeMyPasswordFailedError($this, $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.CHANGEMYPASSWORD.WRONG_USER'));
        }
        $oldPassword = $dataSheet->getRow(0)['OLD_PASSWORD'];
        //$newPassword = $dataSheet->getRow(0)['PASSWORD'];
        try {
            $this->getWorkbench()->getSecurity()->authenticate(new UsernamePasswordAuthToken($user, $oldPassword));
        } catch (\Exception $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            throw new ActionChangeMyPasswordFailedError($this, $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.CHANGEMYPASSWORD.WRONG_PASSWORD'));
        }
        //remove old Password form data sheet (is that needed?)
        $dataSheet->getColumns()->removeByKey('OLD_PASSWORD');
        $undoable = false;
        
        try {
            $affectedRows = $dataSheet->dataUpdate(false, $transaction);
        } catch (\Exception $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            throw new ActionChangeMyPasswordFailedError($this, $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.CHANGEMYPASSWORD.FAILED'));
        }
        
        $result = ResultFactory::createDataResult($task, $dataSheet);
        $result->setMessage($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.UPDATEDATA.RESULT', ['%number%' => $affectedRows], $affectedRows));
        $result->setUndoable($undoable);
        if ($affectedRows > 0) {
            $result->setDataModified(true);
        }
        
        return $result;
    }
    
}
?>