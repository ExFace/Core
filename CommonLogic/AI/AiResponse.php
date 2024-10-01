<?php
namespace exface\Core\CommonLogic\AI;
use exface\Core\CommonLogic\Tasks\ResultMessage;
use exface\Core\Interfaces\AI\AiResponseInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

class AiResponse extends ResultMessage implements AiResponseInterface
{
    private $json = null;
    public function __construct(TaskInterface $prompt, array $json = [])
    {
        parent::__construct($prompt);
        $this->json = $json;
    }

    public function getChoices() : array
    {
        return $this->json['choices'];
    }

    public function toArray() : array
    {
        return $this->json ?? [];
    }
}