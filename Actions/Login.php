<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\CommonLogic\Security\AuthenticationToken\UsernamePasswordAuthToken;
use exface\Core\Factories\ResultFactory;
use exface\Core\Factories\UserFactory;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Actions\iModifyContext;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Factories\SelectorFactory;

/**
 * Performs an authentication attempt using the supplied login data.
 * 
 * This action can perform authentication agains the workbench itself or against a 
 * specified data connection (depending on wheter `CONNECTION` is set in the input
 * data or not).
 * 
 * @author Andrej Kabachnik
 *
 */
class Login extends AbstractAction implements iModifyContext
{
    private $redirectToPage = null;
    
    private $reloadOnSuccess = null;
    
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::SIGN_IN);
        $this->setInputObjectAlias('exface.Core.LOGIN_DATA');
        $this->setInputRowsMax(1);
        $this->setInputRowsMin(1);
    }
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputData = $this->getInputDataSheet($task);
        
        $token = $this->getAuthToken($task);
        if ($connectionSelector = $inputData->getCellValue('CONNECTION', 0)) {
            $dataConnection = DataConnectionFactory::createFromModel($this->getWorkbench(), $connectionSelector);
            $saveCred = $inputData->getCellValue('CONNECTION_SAVE', 0);
            $saveCred = $saveCred === null ? true : BooleanDataType::cast($saveCred);
            if ($saveForUserId = $inputData->getCellValue('CONNECTION_SAVE_FOR_USER', 0)) {
                $dataConnection->authenticate($token, $saveCred, UserFactory::createFromUsernameOrUid($this->getWorkbench(), $saveForUserId));
            } else {
                $dataConnection->authenticate($token, $saveCred);
            }
            if ($this->getReloadOnSuccess($task) === true) {
                $result = ResultFactory::createUriResult($task, '#', $this->translate('RESULT') . ' ' . $this->translate('RESULT_RELOADING'));
            } else {
                $result = ResultFactory::createMessageResult($task, $this->translate('RESULT'));
            }
        } else {
            $this->getWorkbench()->getSecurity()->authenticate($token);
            if ($redirectToSelector = $this->getRedirectToPageSelector($task)) {
                $result = ResultFactory::createRedirectToPageResult($task, $redirectToSelector, $this->translate('RESULT'));
            } else {
                $result = ResultFactory::createRedirectResult($task, '#', $this->translate('RESULT'));
            }
        }
        
        $result->setContextModified(true);
        return $result;
    }
    
    protected function getAuthToken(TaskInterface $task) : AuthenticationTokenInterface
    {
        $inputData = $this->getInputDataSheet($task);
        $inputRow = $inputData->getRow(0);
        if ($tokenClass = $inputData->getCellValue('AUTH_TOKEN_CLASS', 0)) {
            $reflector = new \ReflectionClass($tokenClass);
            $constructorArgs = [];
            foreach ($reflector->getConstructor()->getParameters() as $param) {
                if ($param->getName() === 'facade') {
                    $constructorArgs[] = $task->getFacade();
                } else {
                    foreach ($inputRow as $key => $val) {
                        if (strcasecmp($key, $param->getName()) === 0) {
                            $constructorArgs[] = $val;
                            break;
                        }
                    }
                }
            }
            $token = $reflector->newInstanceArgs($constructorArgs);
        } else {
            $token = new UsernamePasswordAuthToken($inputRow['USERNAME'], $inputRow['PASSWORD'], $task->getFacade());
        }
        return $token;
    }
    
    /**
     *
     * @return UiPageSelectorInterface|null
     */
    public function getRedirectToPageSelector(TaskInterface $task) : ?UiPageSelectorInterface
    {
        if ($this->redirectToPage === null && $this->getReloadOnSuccess($task) === true) {
            if ($task->isTriggeredOnPage()) {
                return $task->getPageSelector();
            } elseif ($this->isDefinedInWidget() && $this->getWidgetDefinedIn()->getPage()->hasModel()) {
                return $this->getWidgetDefinedIn()->getPage();
            }
        }
        return $this->redirectToPage;
    }
    
    /**
     * Id or alias of the page to redirect to after login.
     * 
     * Alternatively use `reload_on_success` to just reload the current page.
     * 
     * @uxon-property redirect_to_page
     * @uxon-type metamodel:page
     * 
     * @param string $value
     * @return Login
     */
    public function setRedirectToPage($pageSeletorOrString) : Login
    {
        if ($pageSeletorOrString instanceof UiPageSelectorInterface) {
            $this->redirectToPage = $pageSeletorOrString;
        } else {
            $this->redirectToPage = SelectorFactory::createPageSelector($this->getWorkbench(), $pageSeletorOrString);
        }
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getReloadOnSuccess(TaskInterface $task) : bool
    {
        if ($this->reloadOnSuccess === null) {
            if ($task->hasInputData() && $col = $task->getInputData()->getColumns()->get('RELOAD_ON_SUCCESS')) {
                return BooleanDataType::cast($col->getCellValue(0));
            } 
        }
        return $this->reloadOnSuccess ?? true;
    }
    
    /**
     * Set to FALSE to prevent the page from reloading after successfull authentication.
     * 
     * @uxon-property reload_on_success
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return Login
     */
    public function setReloadOnSuccess(bool $value) : Login
    {
        $this->reloadOnSuccess = $value;
        return $this;
    }
}