<?php
namespace exface\Core\Interfaces\Tours;

use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * Tour drivers are responsible for generating JS to create interactive tours on the UI.
 * Every tour driver can use different tour libraries like driver.js.
 * 
 * The tour driver libraries should be added to the facade specific TourGuideTraits,
 * for example the UI5TourGuideTrait.php
 */
interface TourDriverInterface extends WorkbenchDependantInterface
{
    public function getFacade() : HttpFacadeInterface;
    
    public function registerWaypointStep(TourStepInterface $step) : TourDriverInterface;

    /**
     * @param TourInterface $tour
     * @return TourStepInterface[]
     */
    public function getTourSteps(TourInterface $tour) : array;
}