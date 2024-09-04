<?php
namespace exface\Core\Facades;

use exface\Core\CommonLogic\AI\AiPrompt;
use exface\Core\CommonLogic\AI\Agents\SqlFilteringAgent;
use exface\Core\Exceptions\Facades\FacadeRoutingError;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
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
 * `POST api/aichat/exface.Core.SqlFilteringAgent/completions`
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
                $prompt = new AiPrompt($this->getWorkbench(), $this, $request);
                $response = $agent->handle($prompt);
                $body = json_encode($response->toArray(), JSON_UNESCAPED_UNICODE);
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
        
        // IDEA Add some extra authentication here?

        
        
        // Add HTTP basic auth for simpler API testing. This allows to log in with
        // username and password from API clients like PostMan.
        $middleware[] = new AuthenticationMiddleware($this, [
            [AuthenticationMiddleware::class, 'extractBasicHttpAuthToken']
        ]);
        
        return $middleware;
    }

    protected function findAgent(string $selector)
    {
        // TODO find agent by selector once an agent list is implemented
        $agent = new SqlFilteringAgent($this->getWorkbench());
        return $agent;
    }
}