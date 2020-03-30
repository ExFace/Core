<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use exface\Core\Factories\ResultFactory;

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
        
        if (! $dataSheet->getColumns()->getByExpression('USER') || ! $dataSheet->getColumns()->getByExpression('PASSWORD') || ! $dataSheet->getColumns()->getByExpression('OLD PASSWORD')) {
            throw new DataSheetColumnNotFoundError($dataSheet, "Can not update password, make sure the data sheet contains the Columns 'USER', 'PASSWORD' and 'OLD PASSWORD'!");
        }
        $user = $dataSheet->getRow(0)['USER'];
        $oldPassword = $dataSheet->getRow(0)['OLD PASSWORD'];
        //$newPassword = $dataSheet->getRow(0)['PASSWORD'];
        $this->getWorkbench()->getSecurity()->authenticate(new UsernamePasswordToken($user, $oldPassword, 'secured_area'));
        
        //remove old Password form data sheet (is that needed?)
        $dataSheet->getColumns()->removeByKey('OLD PASSWORD');
        $undoable = false;
        
        $affectedRows = $dataSheet->dataUpdate(false, $transaction);
        
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