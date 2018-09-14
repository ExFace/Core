<?php
namespace exface\Core\Interfaces\Tasks;

use exface\Core\Interfaces\WidgetInterface;

/**
 * Interfaces for task results of actions, that produce widgets.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ResultWidgetInterface extends ResultInterface
{    
    /**
     * 
     * @return WidgetInterface
     */
    public function getWidget() : WidgetInterface;    
    
    /**
     * 
     * @param WidgetInterface $widget
     * @return ResultWidgetInterface
     */
    public function setWidget(WidgetInterface $widget) : ResultWidgetInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasWidget() : bool;
}