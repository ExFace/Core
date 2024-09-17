<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnectorWithoutTransactions;
use exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class OpenAiConnector extends AbstractDataConnectorWithoutTransactions
{
    private $client = null;

    private $modelName = null;

    private $temperature = null;

    private $url = null;

    private $headers = [];

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
        $json = $this->buildJsonChatCompletionCreate($query);
        $response = $this->sendRequest($json);
        return $query->withResponse($response);
    }

    protected function sendRequest(array $json) : ResponseInterface
    {
        $client = $this->getClient();
        $request = new Request('POST', $this->getUrl(), [], json_encode($json));
        $response = $client->send($request);
        return $response;
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
        $defaults = array();
        $defaults['verify'] = false;
        
        // headers
        if (! empty($this->getHeaders())) {
            $defaults['headers'] = $this->getHeaders();
        }
        
        try {
            $this->setClient(new Client($defaults));
        } catch (\Throwable $e) {
            throw new DataConnectionFailedError($this, "Failed to instantiate HTTP client: " . $e->getMessage(), '6T4RAVX', $e);
        }
    }
    
    /**
     * Returns the initialized Guzzle client
     * 
     * @return Client
     */
    protected function getClient() : Client
    {
        if ($this->client === null) {
            $this->connect();
        }
        return $this->client;
    }
    
    /**
     * 
     * @param Client $client
     * @return OpenAiConnector
     */
    protected function setClient(Client $client) : OpenAiConnector
    {
        $this->client = $client;
        return $this;
    }
    
    protected function performDisconnect() 
    {
        return;
    }

    protected function setUrl(string $url) : OpenAiConnector
    {
        $this->url = $url; 
        return $this;
    }

    /**
     * URL of the external LLM API
     * 
     * @uxon-property url
     * @uxon-type string
     * 
     * @return string
     */
    protected function getUrl() : string
    {
        return $this->url;
    }
    
    /**
     * 
     * @return array
     */
    protected function getHeaders() : array
    {
        return $this->headers;
    }
    
    /**
     * Headers to send with every request
     * 
     * @uxon-property headers
     * @uxon-type object
     * @uxon-template {"api-key": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject|array $value
     * @return OpenAiConnector
     */
    protected function setHeaders($value) : OpenAiConnector
    {
        $this->headers = ($value instanceof UxonObject ? $value->toArray() : $value);
        return $this;
    }
}