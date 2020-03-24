<?php
namespace exface\Core\Actions;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\Selectors\UserSelector;
use exface\Core\CommonLogic\AbstractActionShowDynamicDialog;
use exface\Core\Widgets\Dialog;
use exface\Core\Exceptions\Actions\ActionInputMissingError;

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
class ShowLoginDialog extends AbstractActionShowDynamicDialog
{
    const LOGIN_TO_WORKBENCH = 'workbench';
    const LOGIN_TO_CONNECTION = 'connection';
    
    private $loginTo = self::LOGIN_TO_WORKBENCH;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowDialog::init()
     */
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

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionShowDynamicDialog::enhanceDialogOnActionPerform()
     */
    protected function enhanceDialogOnActionPerform(Dialog $dialog, TaskInterface $task) : Dialog
    {
        $inputData = $this->getInputDataSheet($task);
        
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
        
        return $dialog;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionShowDynamicDialog::enhanceDialogOnActionInit()
     */
    protected function enhanceDialogOnActionInit(Dialog $dialog) : Dialog
    {
        $dialog = parent::enhanceDialogOnActionInit($dialog);
        $dialog->setObjectAlias('exface.Core.LOGIN_DATA');
        $dialog->setColumnsInGrid(1);
        $dialog->setMaximized(false);
        $dialog->setHeight('auto');
        
        $dialog->addWidget(WidgetFactory::createFromUxonInParent($dialog, new UxonObject([
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