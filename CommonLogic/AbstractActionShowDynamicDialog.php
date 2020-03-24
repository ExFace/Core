<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Actions\ShowDialog;
use exface\Core\Widgets\Dialog;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;

/**
 * Base class for actions that show dialogs, where the structure depends on the input data.
 * 
 * Extend this class if you need an action, that creates a dialog on-the-fly based on it's
 * input data or other dynamic parameters. In the simplest case just override these methods:
 * 
 * - `enhanceDialogOnActionInit()` - put all logic, that does not depend on the input data here
 * - `enhanceDialogOnActionPerform()` - all dynamic logic goes here
 * - `perform()` - put any non-dialog-related logic here as always
 * 
 * NOTE: dynamic dialogs are NOT part of the model and NOT part of the page where they are
 * called from. Indeed, the dialog only exists at the moment the action is being performed.
 * 
 * This means, these dialogs cannot use lazy loading or any other logic, that requires UXON
 * configuration. In particular, buttons inside dynamic dialog cannot use action configuration!
 * 
 * This abstract class automatically puts the dialog in a blank page ensuring no page-related
 * logic can be used!
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractActionShowDynamicDialog extends ShowDialog
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $this->enhanceDialogOnActionPerform($this->getDialogWidget(), $task);
        return parent::perform($task, $transaction);
    }

    /**
     * Extend this method to add logic, that depends on dynamic data here (i.e. the $task).
     * 
     * @param Dialog $dialog
     * @param TaskInterface $task
     * @return Dialog
     */
    protected function enhanceDialogOnActionPerform(Dialog $dialog, TaskInterface $task) : Dialog
    {
        return $dialog;
    }
    
    /**
     * Extend this method and add all logic, that does not depend on the input data, here.
     * 
     * @param Dialog $dialog
     * @return Dialog
     */
    protected function enhanceDialogOnActionInit(Dialog $dialog) : Dialog
    {
        return parent::enhanceDialogWidget($dialog);
    }

    /**
     * 
     * @param UiPageInterface $page
     * @param WidgetInterface $contained_widget
     * @return \exface\Core\Widgets\Dialog
     */
    protected final function createDialogWidget(UiPageInterface $page, WidgetInterface $contained_widget = NULL)
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
        return $dialog;
    }
    
    /**
     * 
     * @param Dialog $dialog
     * @return \exface\Core\Widgets\Dialog
     */
    protected final function enhanceDialogWidget(Dialog $dialog)
    {
        return $this->enhanceDialogOnActionInit($dialog);
    }
}