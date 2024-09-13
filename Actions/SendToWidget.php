<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * Sends the input data to a provided widget.
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
    }
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        return ResultFactory::createMessageResult($task, '');
    }
    
    /**
     *
     * @return boolean
     */
    public function getTargetWidgetId()
    {
        return $this->target_widget_id;
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

    /**
     * @inheritDoc
     */
    public function isConfirmationRequiredByDefault(string $confirmationType): bool
    {
        if($confirmationType === self::CONFIRMATION_UNSAVED_CHANGES) {
            return false;
        } else {
            return parent::isConfirmationRequiredByDefault($confirmationType);
        }
    }
}
