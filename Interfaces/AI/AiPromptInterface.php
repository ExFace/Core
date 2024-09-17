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
     * 
     * @return string
     */
    public function getUserPrompt() : string;


    /**
     * 
     * @return string|null
     */
    public function getConversationUid() : ?string;
}