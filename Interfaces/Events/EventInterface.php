<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AliasInterface;

interface EventInterface extends WorkbenchDependantInterface, AliasInterface
{
    /**
     * Prevents propagation of this event to further listeners
     *
     * @return void
     */
    public function stopPropagation();

    /**
     * Returns TRUE if no further listeners will be triggerd by this event or FALSE otherwise
     *
     * @return boolean
     */
    public function isPropagationStopped();
    
    /**
     * 
     * @return string
     */
    public static function getEventName() : string;
}
?>