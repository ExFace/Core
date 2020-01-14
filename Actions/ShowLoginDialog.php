<?php
namespace exface\Core\Actions;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;

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
        
        if ($connectionSelector = $inputData->getCellValue('CONNECTION', 0)) {
            $dataConnection = DataConnectionFactory::createFromModel($this->getWorkbench(), $connectionSelector);
            $dataConnection->createLoginWidget($dialog);
            $dialog
            ->addWidget(WidgetFactory::createFromUxonInParent($dialog, new UxonObject([
                'widget_type' => 'InputHidden',
                'attribute_alias' => 'CONNECTION',
                'value' => $dataConnection->getId()
            ])))
            ->addWidget(WidgetFactory::createFromUxonInParent($dialog, new UxonObject([
                'attribute_alias' => 'CONNECTION__LABEL',
                'readonly' => true,
                'value' => $dataConnection->getName()
            ])));
            
            if ($saveFlag = $inputData->getCellValue('CONNECTION_SAVE', 0)) {
                $dialog
                ->addWidget(WidgetFactory::createFromUxonInParent($dialog, new UxonObject([
                    'attribute_alias' => 'CONNECTION_SAVE',
                    'value' => $saveFlag
                ])));
            }
            
            if ($userId = $inputData->getCellValue('CONNECTION_SAVE_FOR_USER', 0)) {
                $dialog
                ->addWidget(WidgetFactory::createFromUxonInParent($dialog, new UxonObject([
                    'widget_type' => 'InputHidden',
                    'attribute_alias' => 'CONNECTION_SAVE_FOR_USER',
                    'value' => $userId
                ])));
            }
        } else {
            $dialog
            ->addWidget(WidgetFactory::createFromUxonInParent($dialog, new UxonObject([
                'attribute_alias' => 'USERNAME'
            ])))
            ->addWidget(WidgetFactory::createFromUxonInParent($dialog, new UxonObject([
                'attribute_alias' => 'PASSWORD'
            ])));
        }
        
        return parent::perform($task, $transaction);
    }
    
    protected function createDialogWidget(UiPageInterface $page, WidgetInterface $contained_widget = NULL)
    {
        $dialog = parent::createDialogWidget($page, $contained_widget);
        
        $dialog->setObjectAlias('exface.Core.LOGIN_DATA');
        $dialog->setColumnsInGrid(1);
        $dialog->setMaximized(false);
        $dialog->setHeight('auto');
        $dialog->addButton($dialog->createButton(new UxonObject([
            'action_alias' => 'exface.Core.Login',
            'align' => EXF_ALIGN_OPPOSITE,
            'visibility' => WidgetVisibilityDataType::PROMOTED
        ])));
        
        return $dialog;
    }
}