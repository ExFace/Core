<?php
namespace exface\Core\Interfaces\Tours;

use exface\Core\Interfaces\Widgets\WidgetPartInterface;

interface TourStepInterface extends WidgetPartInterface
{
    public function getTitle() : string;
    
    public function getBody() : string;

    /**
     * @return string[]
     */
    public function getWaypoints() : array;
}