<?php
namespace exface\Core\Interfaces\Tours;

use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 * Describes an auto-generated subroute in a tour.
 */
interface TourSubrouteInterface extends iCanBeConvertedToUxon
{
    public function getSubtourType(): string;
}