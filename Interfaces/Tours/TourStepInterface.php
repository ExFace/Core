<?php
namespace exface\Core\Interfaces\Tours;

use exface\Core\Interfaces\Widgets\WidgetPartInterface;

/**
 * Represents a single step in a tour.
 * Tour step is associated with a specific widget and contains information
 * about the title, body text, and the position of the popover that will be displayed when the step is active.
 * 
 * @author Andrej Kabachnik & Sergej Riel
 */
interface TourStepInterface extends WidgetPartInterface
{
    public function getTitle() : string;
    
    public function getBody() : string;
    
    public function getSide() : string;
    
    public function getAlign() : string;

    /**
     * Returns an optional CSS query within the target widget.
     *
     * @return string|null
     */
    public function getFocusCssQuery() : ?string;

    /**
     * Returns the focus area like `myTableId~column.Column1$2` or NULL if not set.
     *
     * @return string|null
     */
    public function getFocusArea() : ?string;

    /**
     * Returns TRUE if the step has a subroute configured.
     *
     * @return bool
     */
    public function hasSubroute() : bool;

    /**
     * Returns the subroute of the step or NULL if there is none.
     *
     * @return TourSubrouteInterface|null
     */
    public function getSubroute() : ?TourSubrouteInterface;
}