<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Tours;

use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\Tours\TourDriverInterface;
use exface\Core\Interfaces\Tours\TourInterface;
use exface\Core\Interfaces\Tours\TourStepInterface;

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
    private array $steps = [];
    
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
     * {@inheritDoc}
     * @see TourDriverInterface::addStep()
     */
    public function registerStep(TourStepInterface $step) : TourDriverInterface
    {
        $this->steps[] = $step;
        return $this;
    }

    /**
     * Gets the steps for the given tour, filtered by the tour's waypoint route and sorted by their order.
     * 
     * {@inheritDoc}
     * @see TourDriverInterface::getTourSteps()
     */
    public function getTourSteps(TourInterface $tour): array
    {
        $steps = [];
        $tourWaypoints = explode("&", $tour->getWaypointsRoute());
        $takeAllWaypoints = in_array("~all", $tourWaypoints);
        
        foreach ($this->steps as $step) {
            // Filter only steps, that have matching waypoints
            $stepWaypoints = $step->getWaypoints();
            
            if ($takeAllWaypoints || !empty(array_intersect($tourWaypoints, $stepWaypoints))) {
                $steps[] = $step;
            }
        }
        
        // sorting steps by order
        usort($steps, function ($a, $b) {
            $aOrder = $a->getPositionInTour();
            $bOrder = $b->getPositionInTour();

            if (!$aOrder && !$bOrder) return 0;
            if (!$aOrder) return 1;
            if (!$bOrder) return -1;

            return $aOrder <=> $bOrder;
        });
        
        return $steps;
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
        
        foreach ($this->getTourSteps($tour) as $step) {
            $aStepsJs .= <<<JS
            
                {
                    element: '#{$step->getElementId($this->getFacade())}',
                    popover: {
                      title: {$this->escapeString($step->getTitle())},
                      description: {$this->escapeString($step->getBody())},
                      side: '{$step->getSide()}',
                      align: '{$step->getAlign()}',
                    }
                },
JS;
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
    
    protected function escapeString($value) : string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}