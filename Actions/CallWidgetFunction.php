<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Actions\iCallWidgetFunction;
use exface\Core\Interfaces\Model\UiPageInterface;

/**
 * Activates a function of a select widget (see available functions in widget docs).
 *  
 * @author andrej.kabachnik
 *
 */
class CallWidgetFunction extends AbstractAction implements iCallWidgetFunction
{

    private $widgetId = null;

    private $funcName = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        $this->setIcon(Icons::MOUSE_POINTER);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        return ResultFactory::createEmptyResult($task);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallWidgetFunction::getFunctionName()
     */
    public function getFunctionName(): ?string
    {
        return $this->funcName;
    }
    
    /**
     * The name of the widget function to call (leave empty to call default function)
     * 
     * @uxon-property function
     * @uxon-type string
     * 
     * @param string $name
     * @return CallWidgetFunction
     */
    public function setFunction(string $name) : CallWidgetFunction
    {
        $this->funcName = ($name === '' ? null : $name);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallWidgetFunction::getWidget()
     */
    public function getWidget(UiPageInterface $page): WidgetInterface
    {
        return $page->getWidget($this->getWidgetId());
    }

    protected function getWidgetId() : string
    {
        return $this->widgetId;
    }
    
    /**
     * The ID of the target widget
     * 
     * @uxon-property widget_id
     * @uxon-type uxon:$..id
     * 
     * @param string $value
     * @return CallWidgetFunction
     */
    public function setWidgetId(string $value) : CallWidgetFunction
    {
        $this->widgetId = $value;
        return $this;
    }
}