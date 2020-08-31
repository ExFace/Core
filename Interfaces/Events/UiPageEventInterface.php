<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Model\UiPageInterface;

/**
 * Interface for events related to UI pages.
 * 
 * @author Andrej Kabachnik
 *
 */
interface UiPageEventInterface extends EventInterface
{
    /**
     * Returns the UI page the event was triggered for.
     * 
     * @return UiPageInterface
     */
    public function getPage() : UiPageInterface;
}