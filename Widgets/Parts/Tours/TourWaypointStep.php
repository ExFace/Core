<?php
namespace exface\Core\Widgets\Parts\Tours;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tours\TourStepInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Represents a single waypoint step in a tour, 
 * which can be associated with a specific widget and contains information 
 * about the content and position of the popover that will be displayed to the user.
 * 
 * A tour_step can be used for unsorted tours, where the order does not metter.
 * For sorted tours please use the "steps" property (TourStoryStep) directly inside the tour definition of a "tour_guide".
 * 
 * A TourDriverInterface implementation collects all TourWaypointSteps that belongs to a Tour via the "waypoints" property.
 * 
 * - `waypoints` array property contains the identifiers of the tours, that this step belongs to. One Step can belong to multiple tours.
 * 
 * ##Examples:
 * 
 * ```
 *  "tour_steps": [
 *      {
 *          "waypoints": [
 *              "news",
 *              "table"
 *          ],
 *          "position_in_tour": 1,
 * 
 *          "title": "New Column",
 *          "body": "This text will appear in the popover when the step is active.",
 *          "side": "bottom",
 *          "align": "center",
 *     },
 * ]
 * ```
 * 
 * @author Sergej Riel
 */
class TourWaypointStep extends AbstractTourStep
{
    use ICanBeConvertedToUxonTrait;

    private WidgetInterface $widget;
    private ?array $waypoints = [];
    private ?int $positionInTour = null;
    private ?UxonObject $waypointsUxon = null;

    public function __construct(WidgetInterface $widget, UxonObject $uxon)
    {
        $this->widget = $widget;
        $this->importUxonObject($uxon);
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

    /**
     * {@inheritDoc}
     * @see TourStepInterface::getWaypoints()
     */
    public function getWaypoints(): array
    {
        return $this->waypoints;
    }

    /**
     * Waypoints are the identifiers of the tours, that this step belongs to. One Step can belong to multiple tours.
     * 
     * @uxon-property waypoints
     * @uxon-type string[]
     * @uxon-template [""]
     * 
     * @param UxonObject $arrayOfWaypoints
     * @return TourStepInterface
     */
    protected function setWaypoints(UxonObject $arrayOfWaypoints) : TourStepInterface
    {
        $this->waypoints = $arrayOfWaypoints->toArray();
        return $this;
    }

    /**
     * @return int|null
     */
    public function getPositionInTour(): ?int
    {
        return $this->positionInTour;
    }

    /**
     * Defines at which point in the tour this step will be displayed to the user.
     * Steps with defined position_in_tour property will be sorted by it,
     * while steps without position_in_tour will be sorted in the order they are defined in the uxon configuration.
     * 
     * *ATTENTION: This property will be deprecated in the future. 
     * For sorted tours please use the "steps" property directly inside the tour definition of a "tour_guide".*
     * 
     * @uxon-property position_in_tour
     * @uxon-type int
     * 
     * @param int $positionInTour
     * @return TourStepInterface
     */
    protected function setPositionInTour(int $positionInTour) : TourStepInterface
    {
        $this->positionInTour = $positionInTour;
        return $this;
    }
}