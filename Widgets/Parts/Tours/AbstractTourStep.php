<?php

namespace exface\Core\Widgets\Parts\Tours;

use Error;
use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Tours\TourStepInterface;
use exface\Core\Interfaces\Tours\TourSubrouteInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\IHaveTourSubrouteInterface;
use exface\Core\Widgets\Filter;

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
 *          "on_next_step": {
 *              "widget_function": "press",
 *              "autostart_tour_id" : "news"
 *          }
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
    private ?TourSubrouteInterface $subroute = null;
    private ?UxonObject $subrouteUxon = null;
    private ?string $title = null;
    private ?string $body = "";
    private ?string $side = null;
    private ?string $align = null;
    private ?string $focusCssQuery = null;
    private $onNextStep = null;
    private $onNextStepUxon = null;

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

    /**
     * {@inheritDoc}
     * @see TourStepInterface::getFocusCssQuery()
     */
    public function getFocusCssQuery() : ?string
    {
        return $this->focusCssQuery;
    }

    /**
     * Highlight the first element matching this CSS query inside the target widget.
     * If no element matches, highlight the target widget itself instead.
     * Omit this property or leave it empty to highlight the whole widget.
     * For story steps, set `widget_to_focus` to define the target widget.
     * Queries depend on the facade's HTML structure; invalid CSS syntax causes a browser error.
     * @uxon-property focus_css_query
     * @uxon-type string
     * @param string $query
     * @return TourStepInterface
     */
    protected function setFocusCssQuery(string $query) : TourStepInterface
    {
        $query = trim($query);
        $this->focusCssQuery = $query === '' ? null : $query;
        return $this;
    }

    /**
     * @return TourOnNextStep
     */
    public function getOnNextStep() : TourOnNextStep
    {
        if ($this->onNextStep === null) {
            $this->onNextStep = new TourOnNextStep($this, $this->onNextStepUxon);
        }
        return $this->onNextStep;
    }

    /**
     * It defines a function that will be executed when the user clicks on the "next" button in the popover of this step.
     * 
     * @uxon-property on_next_step
     * @uxon-type \exface\Core\Widgets\Parts\Tours\TourOnNextStep
     * @uxon-template {"widget_function": "press"}
     * 
     * @param UxonObject $onNextStepUxon
     * @return TourStepInterface
     */
    protected function setOnNextStep(UxonObject $onNextStepUxon) : TourStepInterface
    {
        $this->onNextStepUxon = $onNextStepUxon;
        $this->onNextStep = null;
        return $this;
    }

    /**
     * Returns TRUE if this step has a `subroute` configured.
     * 
     * @return bool
     */
    public function hasSubroute() : bool
    {
        return $this->subrouteUxon !== null;
    }

    /**
     * Returns the subroute of this step or NULL if none is configured.
     * 
     * The subroute is created lazily for the widget returned by `getSubrouteWidget()`. This
     * makes it independent of the order of UXON properties (e.g. `widget_to_focus` of story steps).
     * 
     * @throws WidgetConfigurationError if the widget of the step does not support subroutes
     * @return TourSubrouteInterface|null
     */
    public function getSubroute() : ?TourSubrouteInterface
    {
        if ($this->subroute === null && $this->subrouteUxon !== null) {
            $widget = $this->getSubrouteWidget();
            if (! $widget instanceof IHaveTourSubrouteInterface) {
                $errorWidget = $widget ?? $this->getWidget();
                $type = $widget === null ? 'none' : $widget->getWidgetType();
                throw new WidgetConfigurationError($errorWidget, 'Cannot use a tour step subroute with widget "' . $type . '": this widget does not support tour subroutes.');
            }
            $this->subroute = $widget->getTourSubroute($this->subrouteUxon);
        }
        return $this->subroute;
    }

    /**
     * Returns the widget, that the subroute of this step describes.
     * 
     * By default, this is the widget of the step. Filters are unwrapped to their input widget
     * because they only render that input.
     * 
     * @return WidgetInterface|null
     */
    protected function getSubrouteWidget() : ?WidgetInterface
    {
        $widget = $this->getWidget();
        if ($widget instanceof Filter) {
            $widget = $widget->getInputWidget();
        }
        return $widget;
    }

    /**
     * Add an auto-generated subroute explaining how to use the widget of this step (only if widget supports it!)
     * 
     * The subroute steps are shown right after this step. For story steps, the subroute describes the
     * widget in `widget_to_focus`. Currently `InputComboTable` widgets support subroutes, that explain
     * their lookup dialog. See `InputComboTableSubroute` for available texts.
     * 
     * If the subroute opens a dialog, the tour will continue after it was completed. This requires
     * the tour to have an `id`.
     * 
     * @uxon-property subroute
     * @uxon-type \exface\Core\Widgets\Parts\Tours\InputComboTableSubroute
     * @uxon-template {"lookup_button": "", "lookup_header": "", "lookup_search_button": "", "lookup_table": "", "lookup_selection": "", "lookup_apply_button": ""}
     * 
     * @param UxonObject $uxon
     * @return TourStepInterface
     */
    protected function setSubroute(UxonObject $uxon) : TourStepInterface
    {
        $this->subrouteUxon = $uxon;
        $this->subroute = null;
        return $this;
    }
}