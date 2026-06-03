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
}