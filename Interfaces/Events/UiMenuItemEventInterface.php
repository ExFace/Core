<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Model\UiMenuItemInterface;

/**
 * Interface for events related to UI menu items.
 * 
 * @author Andrej Kabachnik
 *
 */
interface UiMenuItemEventInterface extends EventInterface
{
    /**
     * Returns the UI page the event was triggered for.
     * 
     * @return UiMenuItemInterface
     */
    public function getMenuItem() : UiMenuItemInterface;
}