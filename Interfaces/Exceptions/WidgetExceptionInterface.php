<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\WidgetInterface;

Interface WidgetExceptionInterface
{
    /**
     *
     * @return WidgetInterface
     */
    public function getWidget();
}