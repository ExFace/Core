<?php
namespace exface\Core\Interfaces\Tours;

use exface\Core\Interfaces\Widgets\iHaveCaption;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;

interface TourGuideInterface extends WidgetPartInterface, iHaveCaption
{
    /**
     * @return TourInterface[]
     */
    public function getTours() : array;
}