<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * Interface for events triggered when processing CLI commands.
 * 
 * @author Andrej Kabachnik
 *
 */
interface CliCommandEventInterface extends EventInterface
{
    /**
     * Returns the entire CLI command as a string including all arguments, etc.
     * 
     * @return FacadeInterface
     */
    public function getCliCommand() : string;
}