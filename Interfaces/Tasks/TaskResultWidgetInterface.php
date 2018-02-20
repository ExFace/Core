<?php
namespace exface\Core\Interfaces\Tasks;

use exface\Core\Interfaces\WidgetInterface;

interface TaskResultWidgetInterface extends TaskResultInterface
{
    /**
     * 
     * @return WidgetInterface
     */
    public function getWidget() : WidgetInterface;    
}