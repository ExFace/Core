<?php
namespace exface\Core\Facades;

use exface\Core\CommonLogic\AI\AiPrompt;
use exface\Core\Exceptions\Facades\FacadeRoutingError;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
use exface\Core\Facades\AbstractHttpFacade\Middleware\JsonBodyParser;
use exface\Core\Facades\AbstractHttpFacade\Middleware\TaskReader;
use exface\Core\Factories\AiFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use GuzzleHttp\Psr7\Response;
use exface\Core\DataTypes\StringDataType;
use function GuzzleHttp\Psr7\stream_for;

/**
 * Allows to chat with AI agents defined in the meta model using an OpenAI style API
 * 
 * ## Examples
 * 
 * `POST api/aichat/exface.Core.SqlFilteringAgent/completions?object=exface.Core.USER`
 * 
 * Body:
 * 
 * ```
 * {
 *  "prompt": [
 *   "Show all users added in the past two moths"
 *  ],
 *  "temperature": 0,
 *  "n": 1
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class AiChatFacade extends AbstractHttpFacade
{
    const REQUEST_ATTR_TASK = 'task';

    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $headers = $this->buildHeadersCommon();
        
        // api/aichat/exface.Core.SqlFilterAgent/completions -> exface.Core.SqlFilterAgent/completions
        $pathInFacade = StringDataType::substringAfter($path, $this->getUrlRouteDefault() . '/');
        // exface.Core.SqlFilterAgent/completions -> exface.Core.SqlFilterAgent, completions
        list($agentSelector, $pathInFacade) = explode('/', $pathInFacade, 2);
        $pathInFacade = mb_strtolower($pathInFacade);
        
        // Do the routing here
        switch (true) {     
            case $pathInFacade === 'completions':
                $responseCode = 200;
                $headers['content-type'] = 'application/json';
                $agent = $this->findAgent($agentSelector);
                $prompt = $request->getAttribute(self::REQUEST_ATTR_TASK);
                $response = $agent->handle($prompt);
                $body = json_encode($response->toArray(), JSON_UNESCAPED_UNICODE);
                break;
            case $pathInFacade === 'dmbl':
                $responseCode = 200;
                $headers['content-type'] = 'text/plain';
                $agent = $this->findAgent($agentSelector);
                $prompt = $request->getAttribute(self::REQUEST_ATTR_TASK);
                $body = $agent->getDbmlModel($prompt)->toDBML();
                break;          
            case $pathInFacade === 'LMStudio':
                $responseCode = 200;
                $headers['content-type'] = 'application/json';
                $agent = $this->findAgent($agentSelector);
                $prompt = $request->getAttribute(self::REQUEST_ATTR_TASK);
                $LMStudio = new LMStudio();
                $llmResponse = $LMStudio->getResponse();
                $body = json_encode($llmResponse, JSON_UNESCAPED_UNICODE);
                break;
            default:
                throw new FacadeRoutingError('Route "' . $pathInFacade . '" not found!');
        }
        
        return new Response(($responseCode ?? 404), $headers, stream_for($body ?? ''));
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault(): string
    {
        return 'api/aichat';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getMiddleware()
     */
    protected function getMiddleware() : array
    {
        $middleware = parent::getMiddleware();

        // Parse JSON body if it is a JSON and make it available via `$request->getParsedBody()`
        $middleware[] = new JsonBodyParser();
        
        // Generate a task and save it in the request attributes
        $middleware[] = new TaskReader($this, self::REQUEST_ATTR_TASK, function(AiChatFacade $facade, ServerRequestInterface $request){
            return new AiPrompt($facade->getWorkbench(), $facade, $request); 
        }, 
        // URL parameters, that we need in the task
        [
            'object' => 'object_alias'
        ]);
        
        // Add HTTP basic auth for simpler API testing. This allows to log in with
        // username and password from API clients like PostMan.
        // TODO remove authentication after initial testing phase
        $middleware[] = new AuthenticationMiddleware($this, [
            [AuthenticationMiddleware::class, 'extractBasicHttpAuthToken']
        ]);
        
        return $middleware;
    }

    protected function findAgent(string $selector)
    {
        // TODO find agent by selector once an agent list is implemented
        $agent = AiFactory::createAgentFromString($this->getWorkbench(), $selector);
        return $agent;
    }
}