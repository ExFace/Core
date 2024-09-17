<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnectorWithoutTransactions;
use exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class OpenAiConnector extends AbstractDataConnectorWithoutTransactions
{
    private $modelName = null;

    private $temperature = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     */
    final protected function performQuery(DataQueryInterface $query)
    {
        if (! $query instanceof OpenAiApiDataQuery) {
            throw new DataQueryFailedError($query, 'Invalid query type for connection ' . $this->getAliasWithNamespace() . ': expecting instance of OpenAiApiDataQuery');
        }
        $response = $this->sendRequest($this->buildJsonChatCompletionCreate($query));
        return $query->withResponse($response);
    }

    protected function sendRequest(array $json) : ResponseInterface
    {
        $testJson = [
            "body" => [
                "id" => "cmpl-7QmVI15qgYVllxK0FtxVGG6ywfzaq",
                "created" => 1686617332,
                "choices" => [
                    [
                        "text" => 'Here will be the response from the real LLM',
                        "index" => 0,
                        "finish_reason" => "stop",
                        "logprobs" => null
                    ]
                ],
                "usage" => [
                    "completion_tokens" => 20,
                    "prompt_tokens" => 6,
                    "total_tokens" => 26
                ]
            ]
        ];
        return new Response(200, [], json_encode($testJson));
    }

    protected function buildJsonChatCompletionCreate(OpenAiApiDataQuery $query) : array
    {
        $json = [
            'model' => $this->getModelName($query),
            'messages' => $query->getMessages(true)
        ];

        if (null !== $val = $this->getTemperature($query)) {
            $json['temperature'] = $val;
        }

        return $json;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        // TODO
        return $uxon;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token, bool $updateUserCredentials = true, UserInterface $credentialsOwner = null, bool $credentialsArePrivate = null) : AuthenticationTokenInterface
    {
        // TODO
        return $token;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::createLoginWidget()
     */
    public function createLoginWidget(iContainOtherWidgets $container, bool $saveCredentials = true, UserSelectorInterface $credentialsOwner = null) : iContainOtherWidgets
    {
        // TODO
        return $container;
    }

    /**
     * 
     * @param \exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery $query
     * @return mixed
     */
    public function getTemperature(OpenAiApiDataQuery $query) : ?int
    {
        return $query->getTemperature() ?? $this->temperature;
    }

    /**
     * What sampling temperature to use, between 0 and 2. 
     * 
     * Higher values like 0.8 will make the output more random, while lower values like 0.2 will 
     * make it more focused and deterministic.
     * 
     * If not set, the default of the API will be used.
     * 
     * @param int $val
     * @return \exface\Core\DataConnectors\OpenAiConnector
     */
    protected function setTemperature(int $val) : OpenAiConnector
    {
        $this->temperature = $val;
        return $this;
    }

    /**
     * 
     * @param \exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery $query
     * @return string
     */
    public function getModelName(OpenAiApiDataQuery $query) : string
    {
        return $this->modelName;
    }

    protected function getModelNameDefault() : string
    {
        return 'gpt-4o-mini';
    }

    /**
     * Name of the OpenAI model to call
     * 
     * @uxon-property model
     * @uxon-type string
     * @uxon-default gpt-4o-mini
     * 
     * @param string $name
     * @return \exface\Core\DataConnectors\OpenAiConnector
     */
    protected function setModel(string $name) : OpenAiConnector
    {
        $this->modelName = $name;
        return $this;
    }

    protected function performConnect()
    {
        return;
    }
    
    protected function performDisconnect() 
    {
        return;
    }
}