<?php

namespace exface\Core\Widgets\Parts\Tours;

use Error;
use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tours\TourStepInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Represents a single step in a tour. 
 * A tour step is associated with a specific widget and contains information 
 * about the title, body text, and the position of the popover that will be displayed when the step is active.
 * 
 * - `title`: The title will be displayed at the top of the popover when the step is active.
 * - `body`: The body text can contain a more detailed description and will be displayed below the title in the popover.
 * - `side`: (top, bottom, left, right) The side on which the popover should be displayed referred to the focus area.
 * - `align`: (start, center, end) The alignment of the popover referred to the focus area.
 *
 * ##Examples:
 *
 * ```
 *      {
 *          "title": "New Column",
 *          "body": "This text will appear in the popover when the step is active.",
 *          "side": "bottom",
 *          "align": "center",
 *     },
 * ```
 *
 * @author Sergej Riel
 */
abstract class AbstractTourStep implements TourStepInterface
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

    public function __construct(WidgetInterface $widget, UxonObject $uxon)
    {
        $this->widget = $widget;
        $this->importUxonObject($uxon);
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
}