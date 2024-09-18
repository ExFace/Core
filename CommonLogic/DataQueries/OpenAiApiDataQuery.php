<?php
namespace exface\Core\CommonLogic\DataQueries;

use exface\Core\CommonLogic\Debugger\HttpMessageDebugWidgetRenderer;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\UUIDDataType;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Widgets\DebugMessage;
use Psr\Http\Message\RequestInterface;
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

    private $request = null;

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
     * @param \Psr\Http\Message\RequestInterface $request
     * @return \exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery
     */
    public function withRequest(RequestInterface $request) : OpenAiApiDataQuery
    {
        $clone = clone $this;
        $clone->request = $request;
        return $clone;
    }

    /**
     * 
     * @return RequestInterface
     */
    public function getRequest() : ?RequestInterface
    {
        return $this->request;
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
        return $clone;
    }

    /**
     * 
     * @return array
     */
    public function getResponseData() : array
    {
        if ($this->responseData === null) {
            try {
                $json = JsonDataType::decodeJson($this->getResponse()->getBody()->__toString(), true);
                $this->responseData = $json;
            } catch (\Throwable $e) {
                throw new DataQueryFailedError($this, 'Cannot parse LLM response. ' . $e->getMessage(), null, $e);
            }
        }
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

    public function getResponse() : ResponseInterface
    {
        if ($this->response === null) {
            throw new DataQueryFailedError($this, 'Cannot access LLM response before the query was sent!');
        }
        return $this->response;
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

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataQueries\AbstractDataQuery::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        if (null !== $request = $this->getRequest()) {
            $renderer = new HttpMessageDebugWidgetRenderer($request, ($this->hasResponse() ? $this->getResponse() : null), 'Data request', 'Data response');
            $debug_widget = $renderer->createDebugWidget($debug_widget);
        }
        
        return $debug_widget;
    }
}