<?php
namespace exface\Core\Interfaces\Tasks;

use exface\Core\Interfaces\WidgetInterface;

/**
 * Interfaces for task results of actions, that produce widgets.
 * 
 * @author Andrej Kabachnik
 *
 */
interface TaskResultWidgetInterface extends TaskResultInterface
{
    /**
     * 
     * @param TaskInterface $task
     * @param WidgetInterface $widget
     */
    public function __construct(TaskInterface $task, WidgetInterface $widget = null);
    
    /**
     * 
     * @return WidgetInterface
     */
    public function getWidget() : WidgetInterface;    
    
    /**
     * 
     * @param WidgetInterface $widget
     * @return TaskResultWidgetInterface
     */
    public function setWidget(WidgetInterface $widget) : TaskResultWidgetInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasWidget() : bool;
}