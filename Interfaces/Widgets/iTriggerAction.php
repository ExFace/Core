<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * 
 * @author Andrej Kabachnik
 */
interface iTriggerAction extends WidgetInterface
{
    /**
     * Returns the action object
     *
     * @return ActionInterface
     */
    public function getAction();
}