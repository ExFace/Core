<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tours\TourGuideInterface;
use exface\Core\Interfaces\Widgets\IHaveTourGuideInterface;
use exface\Core\Widgets\Parts\Tours\TourGuide;

/**
 * This trait contains common methods to implement the IHaveTourGuide interface.
 * 
 * @author Andrej Kabachnik
 */
trait IHaveTourGuideTrait {
    
    private TourGuideInterface|UxonObject|null $tourGuide = null;

    /**
     * @see IHaveTourGuideInterface::getTourGuide()
     */
    public function getTourGuide() : ?TourGuideInterface
    {
        if ($this->tourGuide instanceof uxonObject) {
            $this->tourGuide = new TourGuide($this, $this->tourGuide);
        }
        return $this->tourGuide;
    }

    /**
     * Show a tour guide on this widget to start UI tours
     * 
     * @uxon-property tour_guide
     * @uxon-type \exface\Core\Widgets\Parts\Tours\TourGuide
     * @uxon-template {"tours": [{"title": "", "waypoints": ""}]}
     * 
     * @param UxonObject $uxon
     * @return IHaveTourGuideInterface
     */
    public function setTourGuide(UxonObject $uxon) : IHaveTourGuideInterface
    {
        $this->tourGuide = $uxon;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasTourGuide() : bool
    {
        return $this->tourGuide !== null;
    }
}