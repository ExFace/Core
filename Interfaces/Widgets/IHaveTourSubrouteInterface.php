<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tours\TourSubrouteInterface;

interface IHaveTourSubrouteInterface
{
    public function getTourSubroute(UxonObject $config) : TourSubrouteInterface;
}