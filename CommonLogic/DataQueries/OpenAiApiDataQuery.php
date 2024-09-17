<?php
namespace exface\Core\CommonLogic\DataQueries;

use exface\Core\DataTypes\UUIDDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Data query in OpenAI style
 * 
 * Inspired by OpenAI chat completion API: https://platform.openai.com/docs/api-reference/chat/create
 */
class OpenAiApiDataQuery extends AbstractDataQuery
{
    const ROLE_SYSTEM = 'system';
    const ROLE_USER = 'user';
    const ROLE_ASSISTANT = 'assistant';

    private $workbench;

    private $messages = null;

    private $systemPrompt = null;

    private $temperature = null;

    private $conversationUid = null;
    
    private $conversationData = null;

    private $response = null;

    private $responseData = null;

    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }

    /**
     * 
     * @return array
     */
    public function getMessages(bool $includeConversation = false) : array
    {
        $messages = [];
        if ($systemPrompt = $this->getSystemPrompt()) {
            $messages[] = ['content' => $systemPrompt, 'role' => self::ROLE_SYSTEM];
        }
        if ($includeConversation === true) {
            foreach ($this->getConversationData() as $row) {
                $messages[] = ['content' => $row['MESSAGE'], 'role' => $row['ROLE']];
            }
        }
        $messages = array_merge($messages, $this->messages);
        return $messages;
    }

    /**
     * 
     * @param string $content
     * @param string $role
     * @return \exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery
     */
    public function appendMessage(string $content, string $role = self::ROLE_USER) : OpenAiApiDataQuery
    {
        $this->messages[] = ['content' => $content, 'role' => $role];
        return $this;
    }

    /**
     * 
     * @param string $content
     * @param string $role
     * @return \exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery
     */
    public function prependMessage(string $content, string $role) : OpenAiApiDataQuery
    {
        array_unshift($this->messages, ['content'=> $content,'role'=> $role]);
        return $this;
    }

    /**
     * 
     * @param int $temperature
     * @return \exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery
     */
    public function setTemperature(int $temperature) : OpenAiApiDataQuery
    {
        $this->temperature = $temperature;
        return $this;
    }

    /**
     * 
     * @return int|null
     */
    public function getTemperature() : ?int
    {
        return $this->temperature;
    }

    public function setConversationUid(string $conversationUid) : OpenAiApiDataQuery
    {
        $this->conversationUid = $conversationUid;
        return $this;
    }

    public function getConversationUid() : string
    {
        if ($this->conversationUid === null) {
            $this->conversationUid = UUIDDataType::generateSqlOptimizedUuid();
        }
        return $this->conversationUid;
    }

    /**
     * 
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    protected function getConversationData() : DataSheetInterface
    {
        if ($this->conversationData === null) {
            $sheet = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.AI_MESSAGE');
            $sheet->getColumns()->addMultiple([
                'MESSAGE',
                'ROLE'
            ]);
            $sheet->getFilters()->addConditionFromString('AI_CONVERSATION', $this->getConversationUid());
            $sheet->dataRead();
            $this->conversationData = $sheet;
        }
        return $this->conversationData;
    }

    /**
     * 
     * @return string
     */
    public function getSystemPrompt() : ?string
    {
        return $this->systemPrompt;
    }

    /**
     * 
     * @param string $text
     * @return \exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery
     */
    public function setSystemPrompt(string $text) : OpenAiApiDataQuery
    {
        $this->systemPrompt = $text;
        return $this;
    }

    /**
     * 
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return \exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery
     */
    public function withResponse(ResponseInterface $response) : OpenAiApiDataQuery
    {
        $clone = clone $this;
        $clone->response = $response;
        $clone->responseData = json_decode($response->getBody()->__toString(), true);
        return $clone;
    }

    /**
     * 
     * @return array
     */
    public function getResponseData() : array
    {
        return $this->responseData;
    }

    /**
     * 
     * @return bool
     */
    public function hasResponse() : bool
    {
        return $this->response !== null;
    }

    /**
     * 
     * @return array
     */
    public function getResponseChoices() : array
    {
        return $this->responseData['choices'];
    }

    /**
     * 
     * @return array
     */
    public function getResponseUsageStats() : array
    {
        return $this->responseData['usage'];
    }
}