<?php
namespace exface\Core\Widgets\Parts\Tours;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tours\TourGuideInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Traits\iHaveCaptionTrait;

/**
 * TourGuide is a collection of UI tours. 
 * If at least one tour is defined, a tour guide menu dropdown will be shown at the top toolbar 
 * and will contain all the defined tours.
 * 
 * @author Sergej Riel
 */
class TourGuide implements TourGuideInterface
{
    use ICanBeConvertedToUxonTrait;
    use iHaveCaptionTrait;
    
    private WidgetInterface $widget;
    private ?string $title = null;
    private array $tours = [];
    
    public function __construct(WidgetInterface $widget, UxonObject $uxon)
    {
        $this->widget = $widget;
        $this->importUxonObject($uxon);
    }

    /**
     * Defines the UI tours. If at least one tour is defined, a tour guide menu dropdown will be shown at the top toolbar and will contain all the defined tours.
     * waypoints: defines witch waypoints this tour will visit.
     * 
     *  Examples:
     * 
     *  - `news` - only steps with the `news` waypoint
     *  - `~all` - all steps
     *  - `news&intro` - steps with either `news` or `intro` waypoints
     * 
     * @uxon-property tours
     * @uxon-type \exface\Core\Widgets\Parts\Tours\Tour
     * @uxon-template [{"title": "", "waypoints": "~all"}]
     * 
     * @param UxonObject $arrayOfTourDefs
     * @return TourGuideInterface
     */
    protected function setTours(UxonObject $arrayOfTourDefs) : TourGuideInterface
    {
        foreach($arrayOfTourDefs as $uxon) {
            $this->tours[] = new Tour($this->getWidget(), $uxon);
        }
        return $this;
    }

    /**
     * @return array|\exface\Core\Interfaces\Tours\TourInterface[]
     */
    public function getTours() : array
    {
        return $this->tours;
    }

    /**
     * @inheritDoc
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->widget;
    }

    /**
     * @inheritDoc
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }
}