<?php
namespace exface\Core\Widgets\Parts\Tours;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;

/**
 * A tour step generated automatically from a tour subroute.
 * 
 * Subroute steps are not configured in UXON directly. Facade elements generate them from the texts
 * of a subroute (e.g. `InputComboTableSubroute`) because they know the HTML structure of their widget
 * and can provide suitable `focus_css_query` values.
 * 
 * Unlike story steps, a subroute step highlights its own widget. It can also mark the points, where a
 * subroute leaves the current tour (e.g. to open a lookup dialog) and where it returns to the tour,
 * that started the subroute (e.g. after applying the lookup selection).
 * 
 * @author Sergej Riel
 */
class TourSubrouteStep extends AbstractTourStep
{
    private WidgetInterface $widget;
    
    private bool $leavesTour = false;
    
    private bool $returnsToTour = false;
    
    /**
     * Creates a step highlighting the given widget.
     * 
     * @param WidgetInterface $widget
     * @param UxonObject $uxon
     */
    public function __construct(WidgetInterface $widget, UxonObject $uxon)
    {
        $this->widget = $widget;
        $this->importUxonObject($uxon);
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget() : WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }
    
    /**
     * Returns TRUE if this step stops the current tour to continue in another one.
     * 
     * The tour driver remembers the next step of the current tour in this case, so that a later step
     * with `isReturningToTour()` can continue the original tour from there.
     * 
     * @return bool
     */
    public function isLeavingTour() : bool
    {
        return $this->leavesTour;
    }
    
    /**
     * Marks this step as the one leaving the current tour.
     * 
     * @param bool $trueOrFalse
     * @return TourSubrouteStep
     */
    public function setLeavingTour(bool $trueOrFalse) : TourSubrouteStep
    {
        $this->leavesTour = $trueOrFalse;
        return $this;
    }
    
    /**
     * Returns TRUE if this step continues the tour remembered by a previous step leaving its tour.
     * 
     * @return bool
     */
    public function isReturningToTour() : bool
    {
        return $this->returnsToTour;
    }
    
    /**
     * Marks this step as the one returning to the tour, that started the subroute.
     * 
     * @param bool $trueOrFalse
     * @return TourSubrouteStep
     */
    public function setReturningToTour(bool $trueOrFalse) : TourSubrouteStep
    {
        $this->returnsToTour = $trueOrFalse;
        return $this;
    }
}
