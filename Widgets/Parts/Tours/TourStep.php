<?php
namespace exface\Core\Widgets\Parts\Tours;

use Error;
use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\Tours\TourStepInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Represents a single step in a tour, which can be associated with a specific widget and contains information about the content and position of the popover that will be displayed to the user.
 * 
 * - The `waypoints` array property contains the identifiers of the tours, that this step belongs to. One Step can belong to multiple tours.
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
 *          "order_number": 1,
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

class TourStep implements TourStepInterface
{
    use ICanBeConvertedToUxonTrait;

    const SIDE_TOP = 'top';
    const SIDE_BOTTOM = 'bottom';
    const SIDE_LEFT = 'left';
    const SIDE_RIGHT = 'right';
    const ALIGN_START = 'start';
    const ALIGN_CENTER = 'center';
    const ALIGN_END = 'end';

    private WidgetInterface $widget;
    private ?string $title = null;
    private ?string $body = "";
    private ?string $side = null;
    private ?string $align = null;
    private ?array $waypoints = [];
    private ?string $element = null;
    private ?int $orderNumber = null;
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
     * @see TourStepInterface::getTitle()
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * The title of the step. This will be displayed as the title of the popover when the step is active.
     * 
     * @uxon-property title
     * @uxon-type string
     * @uxon-required true
     * @uxon-translatable true
     * 
     * @param string $title
     * @return TourStepInterface
     */
    protected function setTitle(string $title): TourStepInterface
    {
        $this->title = $title;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see TourStepInterface::getBody()
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * The body text of the step, which can contain a more detailed description.
     * 
     * @uxon-property body
     * @uxon-type string
     * 
     * @param string $body
     * @return TourStepInterface
     */
    protected function setBody(string $body): TourStepInterface
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @return string
     */
    public function getSide(): string
    {
        return $this->side ?? 'bottom';
    }

    /**
     * The side on which the popover should be displayed (top, right, bottom, left)
     * 
     * @uxon-property side
     * @uxon-type [top,right,bottom,left]
     * @uxon-template "bottom"
     * 
     * @param string $side
     * @return TourStepInterface
     */
    protected function setSide(string $side): TourStepInterface
    {
        $constant = 'self::SIDE_' . strtoupper($side);
        if (!defined($constant)) { 
            //TODO: the "WidgetPropertyInvalidValueError" have not worked here. Find a better error to drop.
            throw new Error("Invalid tour step side value: $side. Allowed values are: top, right, bottom, left.");
        }
        $this->side = $side;
        return $this;
    }

    /**
     * @return string
     */
    public function getAlign(): string
    {
        return $this->align ?? 'start';
    }

    /**
     * The alignment of the popover (start, center, end)
     * 
     * @uxon-property align
     * @uxon-type [start,center,end]
     * @uxon-template "center"
     * 
     * @param string $align
     * @return TourStepInterface
     */
    protected function setAlign(string $align): TourStepInterface
    {
        $constant = 'self::ALIGN_' . strtoupper($align);
        if (!defined($constant)) {
            throw new Error("Invalid tour step align value: $align. Allowed values are: start, center, end.");
        }
        $this->align = $align;
        return $this;
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
    public function getOrderNumber(): ?int
    {
        return $this->orderNumber;
    }

    /**
     * The order number of the step, which define at which point in the tour this step will be displayed to the user.
     * Steps with defined order numbers will be sorted by their order number,
     * while steps without order number will be sorted in the order they are defined in the uxon configuration.
     * 
     * @uxon-property order_number
     * @uxon-type int
     * 
     * @param int $orderNumber
     * @return TourStepInterface
     */
    protected function setOrderNumber(int $orderNumber) : TourStepInterface
    {
        $this->orderNumber = $orderNumber;
        return $this;
    }
    
    /**
     * This method returns the ID of the widget that this tour step is associated with.
     * The popover for this step will be displayed next to this element.
     * 
     * @param HttpFacadeInterface $facade
     * @return string
     */
    public function getElementId(HttpFacadeInterface $facade): string
    {
        return $facade->getElement($this->widget)->getId();
    }
}