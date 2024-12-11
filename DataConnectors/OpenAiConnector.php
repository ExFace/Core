<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataConnectors\Traits\IDoNotSupportTransactionsTrait;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class OpenAiConnector extends AbstractDataConnector
{
    use IDoNotSupportTransactionsTrait;

    private $client = null;

    private $modelName = null;

    private $temperature = null;

    private $url = null;

    private $headers = [];

    private $dryrun = false;

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
        if ($this->isDryrun()) {
                $response = $this->getDryrunResponse();
        } else {
            try {
                $request = new Request('POST', $this->getUrl(), [], json_encode($json));
                $query = $query->withRequest($request);
                
                $response = $this->sendRequest($request);
            } catch (RequestException $re) {
                if (null !== $response = $re->getResponse()) {
                    $query = $query->withResponse($response);
                }
                throw new DataQueryFailedError($query, 'Error in LLM request. ' . $re->getMessage(), null, $re);
            }
        }
        return $query->withResponse($response);
    }
    
    /**
     * 
     * @param Psr7DataQuery $query
     * @param ResponseInterface $response
     * @param \Throwable $exceptionThrown
     * @return \exface\UrlDataConnector\Exceptions\HttpConnectorRequestError
     */
    protected function createResponseException(Psr7DataQuery $query, ResponseInterface $response = null, \Throwable $exceptionThrown = null)
    {
        if ($response !== null) {
            $message = $this->getResponseErrorText($response, $exceptionThrown);
            $code = $this->getResponseErrorCode($response, $exceptionThrown);
            $level = $this->getResponseErrorLevel($response);
            $ex = new HttpConnectorRequestError($query, $response->getStatusCode(), $response->getReasonPhrase(), $message, $code, $exceptionThrown);
            $useAsTitle = false;
            if ($this->getErrorTextUseAsMessageTitle() === true) {
                $useAsTitle = true;
            } elseif ($this->getErrorTextUseAsMessageTitle() === null) {
                if ($exceptionThrown !== null && $exceptionThrown->getMessage() !== $message) {
                    $useAsTitle = true;
                }
            }
            if ($useAsTitle === true) {
                $ex->setUseRemoteMessageAsTitle(true);
            }
            
            // Wrap the error in an authentication-exception if login failed.
            // This will give facades the option to show a login-screen.
            if ($this->hasAuthentication()) {
                $authFailed = $this->getAuthProvider()->isResponseUnauthenticated($response);
            } else {
                $authFailed = $response->getStatusCode() == 401;
            }
            if ($authFailed && ! ($exceptionThrown instanceof AuthenticationFailedError)) {
                $ex = $this->createAuthenticationException($ex, $message);
            } else {
                if ($level !== null) {
                    $ex->setLogLevel($level);
                }
            }
        } else {
            $ex = new HttpConnectorRequestError($query, 0, 'No Response from Server', $exceptionThrown->getMessage(), null, $exceptionThrown);
        }
        
        return $ex;
    }

    protected function sendRequest(RequestInterface $request) : ResponseInterface
    {
        $client = $this->getClient();
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
        // Overwrite defaults with configured headers and remove empty values afterwards
        return array_filter(array_merge($this->getHeadersDefaults(), $this->headers));
    }

    /**
     * 
     * @return array
     */
    protected function getHeadersDefaults() : array
    {
        return [
            'Content-Type' => 'application/json'
        ];
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

    /**
     * Set to TRUE to return a pregenerated instead of really querying the LLM
     * 
     * @uxon-property dryrun
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return \exface\Core\DataConnectors\OpenAiConnector
     */
    protected function setDryrun(bool $value) : OpenAiConnector
    {
        $this->dryrun = $value;
        return $this;
    }

    protected function isDryrun() : bool
    {
        return $this->dryrun;
    }

    protected function getDryrunResponse() : ResponseInterface
    {
        $json = <<<JSON
      {
    "choices": [
        {
            "content_filter_results": {
                "hate": {
                    "filtered": false,
                    "severity": "safe"
                },
                "self_harm": {
                    "filtered": false,
                    "severity": "safe"
                },
                "sexual": {
                    "filtered": false,
                    "severity": "safe"
                },
                "violence": {
                    "filtered": false,
                    "severity": "safe"
                }
            },
            "finish_reason": "stop",
            "index": 0,
            "logprobs": null,
            "message": {
                "content": "This is an pregenerated demo response because the AI connector has `dryrun:true`. This was not really generated by an LLM!",
                "role": "assistant"
            }
        }
    ],
    "created": 1726608704,
    "id": "chatcmpl-A8a5Q1jUobKy5hhtxR9r1acmuNTi9",
    "model": "gpt-35-turbo",
    "object": "chat.completion",
    "prompt_filter_results": [
        {
            "prompt_index": 0,
            "content_filter_results": {
                "hate": {
                    "filtered": false,
                    "severity": "safe"
                },
                "jailbreak": {
                    "filtered": false,
                    "detected": false
                },
                "self_harm": {
                    "filtered": false,
                    "severity": "safe"
                },
                "sexual": {
                    "filtered": false,
                    "severity": "safe"
                },
                "violence": {
                    "filtered": false,
                    "severity": "safe"
                }
            }
        }
    ],
    "system_fingerprint": null,
    "usage": {
        "completion_tokens": 30,
        "prompt_tokens": 3004,
        "total_tokens": 3034
    }
}  
JSON;

        return new Response(200, [], $json);
    }
}