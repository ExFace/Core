<?php
namespace exface\Core\Actions;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * Sends the input data of the action to a linked widget.
 * 
 * NOTE: Data is sent as-is. Being a front-end action, `SendToWidget` cannot read additional data or apply mappers.
 * 
 * The receiving widget must be configured to be able to accept the data: it must be based on the same object and must
 * handle all required columns. Columns in the action data, that are not explicitly configured in the receiving widget
 * will be ignored. There is no automation like in the case of lazy-loading-actions of data widgets, where the action
 * automatically reads data required for the widget.
 *
 * @author Andrej Kabachnik
 *        
 */
class SendToWidget extends AbstractAction
{
    private $target_widget_id = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::init()
     */
    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setIcon(Icons::SIGN_IN);
        $this->setConfirmationForUnsavedChanges(false);
    }
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        if ($task->hasInputData()) {
            return ResultFactory::createDataResult($task, $task->getInputData());
        } 
        return ResultFactory::createMessageResult($task, '');
    }
    
    /**
     *
     * @return boolean
     */
    public function getTargetWidgetId()
    {
        $widgetDefinedIn = $this->isDefinedInWidget() ? $this->getWidgetDefinedIn() : null;
        return WidgetFactory::ensureIdSpace($this->target_widget_id, null, $widgetDefinedIn);
    }
    
    /**
     * The id of the widget to receive the data.
     *
     * @uxon-property target_widget_id
     * @uxon-type uxon:$..id
     *
     * @param boolean $value
     * @return \exface\Core\Actions\ShowLookupDialog
     */
    public function setTargetWidgetId($value)
    {
        $this->target_widget_id = $value;
        return $this;
    }
}