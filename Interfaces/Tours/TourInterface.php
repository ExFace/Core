<?php
namespace exface\Core\Interfaces\Tours;

interface TourInterface
{
    /**
     * Returns the id of the tour or NULL if it has none.
     *
     * @return string|null
     */
    public function getId() : ?string;
    
    public function getTitle() : string;
    
    public function getDescription() : string;

    /**
     * Whether this tour is hidden from the menu for manual selection.
     *
     * @return bool
     */
    public function getHideInMenu() : bool;

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
    public function getWaypointsRoute() : string;
}