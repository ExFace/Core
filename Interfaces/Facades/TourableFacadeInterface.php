<?php
namespace exface\Core\Interfaces\Facades;

use exface\Core\Interfaces\Tours\TourDriverInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface TourableFacadeInterface extends FacadeInterface
{
    /**
     * @return TourDriverInterface
     */
    public function getTourDriver(WidgetInterface $widget) : TourDriverInterface;
}