<?php
namespace exface\Core\Interfaces\AI;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface AiPromptInterface extends TaskInterface
{
    /**
     * Returns all messages provided as input - system and user messages
     * 
     * @return string[]
     */
    public function getMessages() : array;

    /**
     * 
     * @return string[]
     */
    public function getSystemMessages() : array;

    /**
     * 
     * @return string[]
     */
    public function getUserMessages() : array;
}