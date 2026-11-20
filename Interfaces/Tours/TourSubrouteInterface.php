<?php
namespace exface\Core\Interfaces\Tours;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * Describes an auto-generated subroute in a tour.
 * 
 * A subroute explains how to use a specific widget. Facade elements of the widget generate the
 * actual steps from the subroute configuration because they know the rendered HTML structure.
 * If a subroute continues in another view (e.g. a lookup dialog), the steps there form a hidden
 * tour with the id from `getTourId()`.
 * 
 * @author Sergej Riel
 */
interface TourSubrouteInterface extends iCanBeConvertedToUxon, WorkbenchDependantInterface
{
    /**
     * Returns the type of the subroute - e.g. `InputComboTableSubroute`.
     * 
     * @return string
     */
    public function getSubtourType(): string;
    
    /**
     * Returns the widget explained by this subroute.
     * 
     * @return WidgetInterface
     */
    public function getWidget() : WidgetInterface;
    
    /**
     * Returns a stable id for tours generated for this subroute in other views (e.g. dialogs).
     * 
     * @return string
     */
    public function getTourId() : string;
    
    /**
     * Returns a human-readable title for tours generated from this subroute.
     * 
     * @return string
     */
    public function getTitle() : string;
}