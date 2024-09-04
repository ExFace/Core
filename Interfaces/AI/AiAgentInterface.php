<?php
namespace exface\Core\Interfaces\AI;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface AiAgentInterface
{
    public function handle(AiPromptInterface $prompt) : AiResponseInterface;
}