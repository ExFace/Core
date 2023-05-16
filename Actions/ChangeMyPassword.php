<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\Security\AuthenticationToken\UsernamePasswordAuthToken;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Security\PasswordMismatchError;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\DataTypes\PasswordDataType;
use exface\Core\CommonLogic\Security\AuthenticationToken\MetamodelUsernamePasswordAuthToken;
use exface\Core\Exceptions\Security\AuthenticationIncompleteError;

/**
 * Action to change password of a user. Only for internal purpose, dont use otherwise as it needs very specific input data configuration.
 * 
 * @author rml
 *
 */
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
        $this->setInputRowsMin(1);
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
            throw new ActionInputMissingError($this, "Can not update password, make sure the input data contains the Columns 'USER', 'PASSWORD' and 'OLD_PASSWORD'!");
        }
        $dataSheet->getColumns()->getByExpression('OLD_PASSWORD')->setDataType(PasswordDataType::class);
        $userName = $dataSheet->getRow(0)['USERNAME'];
        if ($userName !== $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUsername()) {
            throw new ActionRuntimeError($this, "Password could not be updated, it is not possible to change the password of another user.");
        }
        $oldPassword = $dataSheet->getRow(0)['OLD_PASSWORD'];
        //$newPassword = $dataSheet->getRow(0)['PASSWORD'];
        try {
            $this->getWorkbench()->getSecurity()->authenticate(
                new MetamodelUsernamePasswordAuthToken($userName, $oldPassword, $task->getFacade())
            );
        } catch (AuthenticationIncompleteError $e) {
            // Ignore second factor here - its OK if the passwords match, complete login is not required.
        } catch (\Exception $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            throw new PasswordMismatchError($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.CHANGEMYPASSWORD.WRONG_PASSWORD'));
        }
        //remove old Password from data sheet (is that needed?)
        $dataSheet->getColumns()->removeByKey('OLD_PASSWORD');
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