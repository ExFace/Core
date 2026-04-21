<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tours\TourGuideInterface;
use exface\Core\Interfaces\Widgets\IHaveTourGuideInterface;
use exface\Core\Widgets\Dialog;
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
        } else if ($this->tourGuide === null) {
            $this->tourGuide = new TourGuide($this, new UxonObject([
                'tours' => []
            ]));
        }
        
        return $this->tourGuide;
    }

    /**
     * Show a tour guide on this widget to start UI tours
     * 
     * @uxon-property tour_guide
     * @uxon-type \exface\Core\Widgets\Parts\Tours\TourGuide
     * @uxon-template {"tours": [{"title": "", "waypoints_route": "~all"}]}
     * 
     * @param UxonObject $uxon
     * @return IHaveTourGuideInterface
     */
    public function setTourGuide(UxonObject $uxon) : IHaveTourGuideInterface
    {
        $this->tourGuide = $uxon;
        
        // Give the tours to the dialog we are in (or to the page
        // root if not in a dialog)
        if ($this->hasParent()) {
            
            // If this widget is a dialog itself, we do not add the tour to the parent.
            if ($this->getWidgetType() === 'Dialog') {
                return $this;
            }
            
            $container = $this->getParentByClass(Dialog::class);
            if ($container === null) {
                $container = $this->getPage()->getWidgetRoot();
            }
            if ($container instanceof IHaveTourGuideInterface) {
                foreach($this->getTourGuide()->getTours() as $tour) {
                    if ($tour !== null) {
                        $container->getTourGuide()->addTour($tour);
                    }
                }
            }
        }
        
        return $this;
    }

    /**
     * Tells whether this widget has a tour guide with at least one tour defined.
     * 
     * @return bool
     */
    public function hasTourGuide() : bool
    {
        return $this->tourGuide !== null;
    }
}