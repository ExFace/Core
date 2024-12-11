<?php
namespace exface\Core\Interfaces\AI;
use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface AiAgentInterface extends iCanBeConvertedToUxon
{
    public function handle(AiPromptInterface $prompt) : AiResponseInterface;
}