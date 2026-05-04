<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Tours;

use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\Tours\TourDriverInterface;
use exface\Core\Interfaces\Tours\TourInterface;
use exface\Core\Interfaces\Tours\TourStepInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Filter;
use exface\Core\Widgets\Parts\Tours\TourStoryStep;

/**
 * This class is a tour driver that uses the driver.js library to create interactive tours on the UI.
 * 
 * The definition of the tour is provided by the Tour.php class 
 * and all the corresponding steps are provided by the TourStep.php class.
 * 
 * @author: Sergej Riel
 */
class DriverJsTourDriver implements TourDriverInterface
{
    private HttpFacadeInterface $facade;
    private array $waypointSteps = [];
    
    public function __construct(HttpFacadeInterface $httpFacade)
    {
        $this->facade = $httpFacade;
    }

    /**
     * {@inheritDoc}
     * @see TourDriverInterface::getFacade()
     */
    public function getFacade(): HttpFacadeInterface
    {
        return $this->facade;
    }

    /**
     * Registers a tour waypoint step.
     * 
     * {@inheritDoc}
     * @see TourDriverInterface::registerWaypointStep()
     */
    public function registerWaypointStep(TourStepInterface $step) : TourDriverInterface
    {
        $this->waypointSteps[] = $step;
        return $this;
    }

    /**
     * Gets the steps (TourWaypointStep and TourStoryStep) for the given tour, filtered by the tour's waypoint route and sorted by their order.
     * 
     * {@inheritDoc}
     * @see TourDriverInterface::getTourSteps()
     */
    public function getTourSteps(TourInterface $tour): array
    {
        $steps = [];
        $tourWaypoints = explode("&", $tour->getWaypointsRoute());
        $takeAllWaypoints = in_array("~all", $tourWaypoints);
        
        // * TourWaypointSteps:
        foreach ($this->waypointSteps as $step) {
            
            // Filter only steps, that have matching waypoints
            $stepWaypoints = $step->getWaypoints();
            
            if ($takeAllWaypoints || !empty(array_intersect($tourWaypoints, $stepWaypoints))) {
                $steps[] = $step;
            }
        }
        
        // Sorts the steps based on the order of the waypoints in the tour's waypoint route
        // and then by their position_in_tour property.
        usort($steps, function ($a, $b) use ($tourWaypoints, $takeAllWaypoints) {
            $aWaypoints = $a->getWaypoints();
            $bWaypoints = $b->getWaypoints();

            if ($takeAllWaypoints) {
                $aGroup = $this->getStepWaypointSortValue($aWaypoints);
                $bGroup = $this->getStepWaypointSortValue($bWaypoints);
            } else {
                $aGroup = $this->getFirstMatchingWaypointIndex($aWaypoints, $tourWaypoints);
                $bGroup = $this->getFirstMatchingWaypointIndex($bWaypoints, $tourWaypoints);
            }

            if ($aGroup !== $bGroup) {
                return $aGroup <=> $bGroup;
            }

            $aOrder = $a->getPositionInTour();
            $bOrder = $b->getPositionInTour();

            if (!$aOrder && !$bOrder) return 0;
            if (!$aOrder) return 1;
            if (!$bOrder) return -1;

            return $aOrder <=> $bOrder;
        });

        // * TourStorySteps:
        // Adds the tour stroy steps after the waypoint steps.
        if (null !== $tourStorySteps = $tour->getTourStorySteps()) {
            $steps = array_merge($steps, $tourStorySteps);
        }
        
        return $steps;
    }

    /**
     * Gets the index of the first matching waypoint in the tour's waypoint route for the given step waypoints.
     * 
     * @param array $stepWaypoints
     * @param array $tourWaypoints
     * @return int
     */
    private function getFirstMatchingWaypointIndex(array $stepWaypoints, array $tourWaypoints): int
    {
        foreach ($tourWaypoints as $index => $tourWaypoint) {
            if (in_array($tourWaypoint, $stepWaypoints, true)) {
                return $index;
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * Gets a string value for sorting the steps that have the same waypoints, 
     * by sorting their waypoints and concatenating them.
     * 
     * @param array $stepWaypoints
     * @return string
     */
    private function getStepWaypointSortValue(array $stepWaypoints): string
    {
        sort($stepWaypoints, SORT_NATURAL | SORT_FLAG_CASE);
        return implode('|', $stepWaypoints);
    }

    /**
     * @inheritDoc
     */
    public function getWorkbench()
    {
        return $this->facade->getWorkbench();
    }

    /**
     * Builds the JavaScript code to start the tour using driver.js library, 
     * based on the steps of the given tour.
     * 
     * @param TourInterface $tour
     * @return string
     */
    public function buildJsStartTour(TourInterface $tour) : string
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $aStepsJs = '';
        
        $steps = $this->getTourSteps($tour);
        if (empty ($steps))  {
            $aStepsJs .= <<<JS
                {
                    popover: {
                      title: {$this->escapeString('This tour is empty')}
                    }
                },
JS;
        } else {
            foreach ($steps as $step) {
                $aStepsJs .= <<<JS
                {
                    element: '#{$this->getStepHighlightedElementId($step)}',
                    popover: {
                      title: {$this->escapeString($step->getTitle())},
                      description: {$this->escapeString($step->getBody())},
                      side: '{$step->getSide()}',
                      align: '{$step->getAlign()}',
                      {$this->buildJsOnNextClick($step)}
                    }
                },
JS;
            }
        }
        $aStepsJs = '[' . $aStepsJs . ']';
        
        $driverJs = <<<JS

            const driverObj = driver.js.driver({
                showProgress: '{$tour->getShowProgress()}',
                disableActiveInteraction: '{$tour->getDisableActiveInteraction()}',
                nextBtnText: '{$translator->translate('TOUR.STEP.ACTION.NEXT')}',
                prevBtnText: '{$translator->translate('TOUR.STEP.ACTION.PREVIOUS')}',
                doneBtnText: '{$translator->translate('TOUR.STEP.ACTION.DONE')}',
                steps: {$aStepsJs}
            });
    
            driverObj.drive();
            
JS;

     return $driverJs;
    }

    /**
     * Builds the JavaScript code for the onNextClick event of a tour step, 
     * which will be executed when the user clicks the "Next" button in the popover of that step.
     * 
     * @param TourStepInterface $step
     * @return string
     */
    protected function buildJsOnNextClick(TourStepInterface $step) :string
    {
        $onNextStepFunction = $step->getOnNextStepFunction();
        if (($functionName = $onNextStepFunction->getFunctionName()) === null) {
            return '';
        }
        
        $focusedWidget = $this->getFocusedWidget($step);
        
        //function call:
        try {
            //TODO: If this is in a dialog, it currently gets one step from the called page and tries to click on it.
            // At this case the controller of the called page is not found and we getting an error here.
            // The Quick fix is to catch the missing controller error.
            // A propper Fix must be implemented! For that, start at the point where the "registerWaypointStep()" is called.
            $callFunctionJs = $this->getFacade()?->getElement($focusedWidget)?->buildJsCallFunction($functionName);
        } catch (\Exception $e) {
            $callFunctionJs = 'console.warn(' . $this->escapeString($e->getMessage()) . ');';
        }
        
        //set the tour of that step to pending, so it will autostart if the view with that tour is loaded:
        $pendingDialogTourJs = '';
        $autostartTourId = $onNextStepFunction->getAutostartTourId();
        
        if ($autostartTourId) {
            $pendingDialogTourJs = <<<JS
                window.exfTourContext.setPendingTour({
                    targetTourId: {$this->escapeString($autostartTourId)}
                });
JS;
        }
        
        return <<<JS

          onNextClick: (element, step, { driver }) => {
              if (element) {
                  {$callFunctionJs}
                
                  {$pendingDialogTourJs}
              }
              driver.moveNext();
          }
JS;
    }
    
    protected function escapeString($value) : string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * This method returns the ID of the DOM element widget that this step will highlight.
     * 
     * The popover for this step will be displayed next to this element.
     * 
     * @param TourStepInterface $step
     * @return string
     */
    public function getStepHighlightedElementId(TourStepInterface $step): string
    {
        // If there is no widget to focus, we return a dummy string,
        // which means that the popover will be displayed in the center of the screen
        // without highlighting any element.
        if ( null === $focusedWidget = $this->getFocusedWidget($step)) {
            return 'exf-tour-step-no-highlight';
        } else {
            return $this->getFacade()->getElement($focusedWidget)->getId();
        }
    }

    /**
     * Returns the widget that should be focused (highlighted) in the given step.
     * 
     * @param TourStepInterface $step
     * @return WidgetInterface|null
     */
    protected function getFocusedWidget(TourStepInterface $step) : ?WidgetInterface
    {
        if ($step instanceof TourStoryStep) {
            $widget = $step->getWidgetToFocus() ? $step->getWidgetToFocus()->getTargetWidget() : null;
        } else {
            $widget = $step->getWidget();
        }

        // Filters will mostly not have any id - they just render their input_widget, so we take that
        if ($widget instanceof Filter) {
            $widget = $widget->getInputWidget();
        }
        return $widget;
    }
}