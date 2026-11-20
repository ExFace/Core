<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Interfaces;

use exface\Core\Interfaces\Tours\TourStepInterface;
use exface\Core\Interfaces\Tours\TourSubrouteInterface;

/**
 * Facade elements implementing this interface can render steps for tour subroutes of their widget.
 * 
 * Subroute steps highlight parts of the rendered element (e.g. the lookup button of a combo), so they
 * depend on the HTML structure of the facade. The tour driver inserts these steps right after the step,
 * that configures the subroute.
 * 
 * @author Sergej Riel
 */
interface AjaxFacadeElementTourSubrouteInterface extends AjaxFacadeElementInterface
{
    /**
     * Returns the steps to show in the current view right after the step with the given subroute.
     * 
     * Steps leading to another view (e.g. opening a dialog) should autostart the tour with the id
     * from `$subroute->getTourId()` and be marked via `TourSubrouteStep::setLeavingTour()`.
     * 
     * @param TourSubrouteInterface $subroute
     * @return TourStepInterface[]
     */
    public function getTourSubrouteSteps(TourSubrouteInterface $subroute) : array;
}