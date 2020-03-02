<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\Security\AuthenticationToken\AnonymousAuthToken;
use exface\Core\Interfaces\Actions\iModifyContext;

/**
 * Logs out the currently authenticated user.
 * 
 * @author Andrej Kabachnik
 *
 */
class Logout extends AbstractAction implements iModifyContext
{
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::SIGN_OUT);
    }
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $this->getWorkbench()->getSecurity()->authenticate(new AnonymousAuthToken($this->getWorkbench()));
        $result = ResultFactory::createRedirectToPageResult($task, $task->getPageSelector(), $this->translate('RESULT'));
        $result->setContextModified(true);
        return $result;
    }
}