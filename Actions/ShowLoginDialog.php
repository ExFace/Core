<?php
namespace exface\Core\Actions;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\Selectors\UserSelector;
use exface\Core\Factories\UiPageFactory;

/**
 * Shows a login dialog.
 * 
 * The login dialog has two operation modes:
 * - logging in to the workbench. 
 * - logging in to a specific data connection. Here, the contents of the dialog is determined 
 * by the connector used. If successfull, the supplied credentials are stored as a credential 
 * set for the current user and the the data connection. 
 * 
 * The login-dialog expects input data based on the object `exface.Core.LOGIN_DATA`. The
 * operation mode is selected automatically depending on whether the input data has a 
 * `CONNECTION` defined or not.
 * 
 * @author Andrej Kabachnik
 *
 */
class ShowLoginDialog extends ShowDialog
{
    const LOGIN_TO_WORKBENCH = 'workbench';
    const LOGIN_TO_CONNECTION = 'connection';
    
    private $loginTo = self::LOGIN_TO_WORKBENCH;
    
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::KEY);
        $this->setInputObjectAlias('exface.Core.LOGIN_DATA');
        $this->setInputRowsMax(1);
        $this->setPrefillWithInputData(true);
        $this->setPrefillWithPrefillData(true);
        $this->setPrefillWithFilterContext(false);
    }
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputData = $this->getInputDataSheet($task);
        
        $dialog = $this->getDialogWidget();
        
        if ($this->getLoginTo() === self::LOGIN_TO_CONNECTION) {
            if (! $connectionSelector = $inputData->getCellValue('CONNECTION', 0)) {
                throw new ActionInputMissingError($this, 'No data connection to log in to: please provide a CONNECTION in input data!');
            }
            $dataConnection = DataConnectionFactory::createFromModel($this->getWorkbench(), $connectionSelector);
            $loginPrompt = $dialog->getWidgetFirst();
            $saveCreds = BooleanDataType::cast($inputData->getCellValue('CONNECTION_SAVE', 0));
            $saveFor = $inputData->getCellValue('CONNECTION_SAVE_FOR_USER', 0);
            $saveForSelector = $saveFor ? new UserSelector($this->getWorkbench(), $saveFor) : null;
            $loginPrompt = $dataConnection->createLoginWidget($loginPrompt, $saveCreds, $saveForSelector);
        }
        
        return parent::perform($task, $transaction);
    }
    
    protected function createDialogWidget(UiPageInterface $page, WidgetInterface $contained_widget = NULL)
    {
        /* @var $dialog \exface\Core\Widgets\Dialog */
        $dialog = WidgetFactory::create(UiPageFactory::createEmpty($this->getWorkbench()), $this->getDefaultWidgetType());
        $dialog->setMetaObject($this->getMetaObject());
        
        if ($contained_widget) {
            $dialog->addWidget($contained_widget);
            if (false === $contained_widget->getWidth()->isUndefined()) {
                $dialog->setWidth($contained_widget->getWidth()->getValue());
            }
            if (false === $contained_widget->getHeight()->isUndefined()) {
                $dialog->setHeight($contained_widget->getHeight()->getValue());
            }
        }
        
        $dialog->setObjectAlias('exface.Core.LOGIN_DATA');
        $dialog->setColumnsInGrid(1);
        $dialog->setMaximized(false);
        $dialog->setHeight('auto');
        
        $dialog
        ->addWidget(WidgetFactory::createFromUxonInParent($dialog, new UxonObject([
            'widget_type' => 'LoginPrompt'
        ])));
        $dialog->setHideCloseButton(true);
        
        return $dialog;
    }
    
    /**
     *
     * @return string
     */
    public function getLoginTo() : string
    {
        return $this->loginTo;
    }
    
    /**
     * Log in to the workbench (default) or a specific data connection?
     * 
     * @uxon-property login_to
     * @uxon-type [workbench,connection]
     * @uxon-default workbench
     * 
     * @param string $value
     * @return ShowLoginDialog
     */
    public function setLoginTo(string $value) : ShowLoginDialog
    {
        $constName = 'self::LOGIN_TO_' . mb_strtoupper($value);
        if (! defined($constName)) {
            throw new ActionConfigurationError($this, 'Invalid value "' . $value . '" for property "login_to" of action "' . $this->getAliasWithNamespace() . '"!');
        }
        $this->loginTo = constant($constName);
        return $this;
    }
}