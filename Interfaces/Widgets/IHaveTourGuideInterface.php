<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Tours\TourGuideInterface;

interface IHaveTourGuideInterface
{
    /**
     * @return TourGuideInterface|null
     */
    public function getTourGuide() : ?TourGuideInterface;

    /**
     * @return bool
     */
    public function hasTourGuide() : bool;
}