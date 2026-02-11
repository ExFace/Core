<?php
namespace exface\Core\Interfaces\Tours;

use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;

interface TourDriverInterface extends WorkbenchDependantInterface
{
    public function getFacade() : HttpFacadeInterface;
    
    public function registerStep(TourStepInterface $step) : TourDriverInterface;

    /**
     * @param TourInterface $tour
     * @return TourStepInterface[]
     */
    public function getTourSteps(TourInterface $tour) : array;
}