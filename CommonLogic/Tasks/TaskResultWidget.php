<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Tasks\TaskResultWidgetInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

/**
 * Generic task result implementation.
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskResultWidget extends TaskResultMessage implements TaskResultWidgetInterface
{
    private $widget = null;
    
    /**
     * 
     * @param TaskInterface $task
     * @param WidgetInterface $widget
     */
    public function __construct(TaskInterface $task, WidgetInterface $widget = null)
    {
        parent::__construct($task);
        $this->widget = $widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultWidgetInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultWidgetInterface::setWidget()
     */
    public function setWidget(WidgetInterface $widget): TaskResultWidgetInterface
    {
        $this->widget = $widget;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultWidgetInterface::hasWidget()
     */
    public function hasWidget(): bool
    {
        return is_null($this->widget) ? false : true;
    }

}