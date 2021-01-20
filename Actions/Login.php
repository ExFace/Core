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
use exface\Core\Interfaces\Tasks\HttpTaskInterface;

/**
 * Performs an authentication attempt using the supplied login data.
 * 
 * This action can perform authentication agains the workbench itself or against a 
 * specified data connection (depending on whether `CONNECTION` is set in the input
 * data or not).
 * 
 * Requires input data based on the meta object `exface.Core.LOGIN_DATA`. The input
 * data should contain exactly one row with all information required to create an
 * authentication token in its cells. 
 * 
 * The following input columns control, how the data is processed:
 * 
 * - `CONNECTION` - if set, authentication will be performed against this data connection
 * (the value MUST be a valid connection selector like an alias or UID)
 * - `CONNECTION_SAVE` - if a truthly value is passed (`1` or `true`), a successfull
 * login will produce connection credentials. Only applicable in connection-mode.
 * - `CONNECTION_SAVE_FOR_USER` - if `CONNECTION_SAVE`, this column can be used to
 * explicitly specify a the future owner of the credential set. Expects a valid user
 * selector (username or UID).
 * - `AUTH_TOKEN_CLASS` - the qualified PHP class name of the authentication token to
 * use. All the other columns of the input data will be treated as named constructor
 * arguments for the token: e.g. for `MyToken::__construct($user, $key)`, the values of
 * the columns `USER` and `KEY` (case insensitive!) will automatically be used when calling
 * the constructor. Additionally there are some reserved constructor argument names with
 * the following behavior (they cannot be passed with the input data!):
 *      - `$facade` will receive the instance of the facade the called the login action
 *      - `$request` will receive the PSR7 request instance used to call the action or
 *      `null` if the task is not an `HttpTask`
 * 
 * If no column `AUTH_TOKEN_CLASS` exists or if it's empty, the default `UsernamePasswordAuthToken` 
 * will be used. 
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
                // See if we can find a value for every constructor parameter
                switch (mb_strtolower($param->getName())) {
                    // Parameter name $facade is reserved for the calling facade
                    case 'facade':
                        $constructorArgs[] = $task->getFacade();
                        break;
                    // Parameter name $request is reserved for the HTTP request (if known)
                    case 'request':
                        $constructorArgs[] = $task instanceof HttpTaskInterface ? $task->getHttpRequest() : null;
                        break;
                    // All other constructor parameters should be found in the 
                    // input data
                    default:
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::isTriggerWidgetRequired()
     */
    public function isTriggerWidgetRequired() : ?bool
    {
        return false;
    }
}