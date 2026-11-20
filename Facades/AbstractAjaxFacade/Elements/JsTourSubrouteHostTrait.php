<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tours\TourStepInterface;
use exface\Core\Interfaces\Tours\TourSubrouteInterface;
use exface\Core\Interfaces\Widgets\IHaveTourGuideInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Parts\Tours\Tour;

/**
 * Generates hidden tours for tour subroutes, that continue in the widget of this element.
 * 
 * Some subroutes leave the current view - e.g. the subroute of an `InputComboTable` opens its
 * lookup dialog. The step leading there auto-starts a tour with the id of the subroute. This trait
 * creates that tour in the widget of the element using it (the "host" - e.g. the lookup dialog).
 * 
 * Only the host element knows its rendered structure, so it must implement `buildTourSubrouteSteps()`.
 * Call `addTourSubrouteTours()` in the `init()` method of the element - before the tour guide is rendered.
 * 
 * @author Sergej Riel
 */
trait JsTourSubrouteHostTrait
{
    /**
     * Returns the steps explaining the host widget for the given subroute.
     * 
     * Return an empty array for unsupported subroutes - no tour will be created for them then.
     * The last step should be marked via `TourSubrouteStep::setReturningToTour()` to let the original
     * tour continue.
     * 
     * @param TourSubrouteInterface $subroute
     * @return TourStepInterface[]
     */
    abstract protected function buildTourSubrouteSteps(TourSubrouteInterface $subroute) : array;
    
    /**
     * Adds a hidden tour to the host widget for every subroute of the given widget.
     * 
     * Tours already present in the host (e.g. because tour guides were copied) are not added twice.
     * No tour guide is created if there are no subroutes - an empty guide would render an empty tour menu.
     * 
     * @param WidgetInterface $subrouteWidget
     * @return void
     */
    protected function addTourSubrouteTours(WidgetInterface $subrouteWidget) : void
    {
        $host = $this->getWidget();
        if (! $host instanceof IHaveTourGuideInterface) {
            return;
        }
        
        $subroutes = $this->findTourSubroutes($subrouteWidget);
        if ($subroutes === []) {
            return;
        }
        
        $existingTourIds = [];
        if ($host->hasTourGuide()) {
            foreach ($host->getTourGuide()->getTours() as $tour) {
                if ($tour->getId() !== null) {
                    $existingTourIds[$tour->getId()] = true;
                }
            }
        }
        
        foreach ($subroutes as $tourId => $subroute) {
            if (array_key_exists($tourId, $existingTourIds)) {
                continue;
            }
            $steps = $this->buildTourSubrouteSteps($subroute);
            if ($steps === []) {
                continue;
            }
            $tour = new Tour($host, new UxonObject([
                'id' => $tourId,
                'title' => $subroute->getTitle(),
                'hide_in_menu' => true
            ]));
            foreach ($steps as $step) {
                $tour->addStep($step);
            }
            $host->getTourGuide()->addTour($tour);
        }
    }
    
    /**
     * Returns all subroutes explaining the given widget indexed by their tour id.
     * 
     * Subroutes may be defined in the `tour_steps` of the widget itself or in story steps of tours
     * of any of its parents. Tours are often copied to dialogs or the page root, so the same subroute
     * may be found multiple times - the tour id makes them unique.
     * 
     * @param WidgetInterface $subrouteWidget
     * @return TourSubrouteInterface[]
     */
    protected function findTourSubroutes(WidgetInterface $subrouteWidget) : array
    {
        $steps = $subrouteWidget->getTourSteps();
        $widget = $subrouteWidget;
        while ($widget !== null) {
            if ($widget instanceof IHaveTourGuideInterface && $widget->hasTourGuide()) {
                foreach ($widget->getTourGuide()->getTours() as $tour) {
                    foreach ($tour->getTourStorySteps() ?? [] as $step) {
                        $steps[] = $step;
                    }
                }
            }
            $widget = $widget->hasParent() ? $widget->getParent() : null;
        }
        
        $subroutes = [];
        foreach ($steps as $step) {
            if (! $step instanceof TourStepInterface || ! $step->hasSubroute()) {
                continue;
            }
            $subroute = $step->getSubroute();
            if ($subroute !== null && $subroute->getWidget() === $subrouteWidget) {
                $subroutes[$subroute->getTourId()] = $subroute;
            }
        }
        return $subroutes;
    }
}
