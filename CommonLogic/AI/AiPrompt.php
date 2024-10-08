<?php
namespace exface\Core\CommonLogic\AI;
use exface\Core\CommonLogic\Tasks\HttpTask;
use exface\Core\Interfaces\AI\AiPromptInterface;

class AiPrompt extends HttpTask implements AiPromptInterface
{
    public function getMessages() : array
    {
        $params = $this->getParameters();
        return ($params['messages'] ?? $params['prompt']) ?? [];
    }

    public function getUserPrompt() : string
    {
        return implode(PHP_EOL, $this->getUserMessages());
    }

    /**
     * 
     * @see \exface\Core\Interfaces\AI\AiPromptInterface::getConversationUid()
     */
    public function getConversationUid() : ?string
    {
        return $this->getParameter('conversation');
    }

    public function getUserMessages() : array
    {
        $array = array_filter($this->getMessages(), function($msg) {
            if (is_string($msg)) {
                return true;
            } else {
                return $msg['role'] === 'user';
            }
        });
        return $array;
    }

    public function getSystemMessages() : array
    {
        return array_filter($this->getMessages(), function($msg) {
            if (is_string($msg)) {
                return false;
            } else {
                return $msg['role'] === 'system';
            }
        });
    }
}