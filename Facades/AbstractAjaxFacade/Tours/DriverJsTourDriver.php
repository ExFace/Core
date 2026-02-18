<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Tours;

use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\Tours\TourDriverInterface;
use exface\Core\Interfaces\Tours\TourInterface;
use exface\Core\Interfaces\Tours\TourStepInterface;

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
     * {@inheritDoc}
     * @see TourDriverInterface::getTourSteps()
     */
    public function getTourSteps(TourInterface $tour): array
    {
        $steps = [];
        foreach ($this->steps as $step) {
            // Filter only steps, that have matching waypoints
            $steps[] = $step;
        }
        return $steps;
    }

    /**
     * @inheritDoc
     */
    public function getWorkbench()
    {
        $this->facade->getWorkbench();
    }
    
    public function buildJsStartTour(TourInterface $tour) : string
    {

        $aStepsJs = '';
        foreach ($this->getTourSteps($tour) as $step) {
            $aStepsJs .= "{title: {$this->escapeString($step->getTitle())}},";
        }
        $aStepsJs = '[' . $aStepsJs . ']';
        return <<<JS

                    const aSteps = $aStepsJs;
                    console.log(aSteps);
                    alert('Tours will be ready soon!');
JS;

    }
    
    protected function escapeString($value) : string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}