<?php
namespace exface\Core\Interfaces\Tours;

interface TourInterface
{
    public function getTitle() : string;
    
    public function getDescription() : string;

    /**
     * @return TourStepInterface[]
     */
    public function getSteps(TourDriverInterface $driver) : array;

    /**
     * Which waypoints this tour will visit.
     * 
     * Examples:
     * 
     * - `news` - only steps with the `news` waypoint
     * - `~all` - all steps
     * - `news&intro` - steps with either `news` or `intro` waypoints
     * 
     * @return string
     */
    public function getWaypointRoute() : string;
}