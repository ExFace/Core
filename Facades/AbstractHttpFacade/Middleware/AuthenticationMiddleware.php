<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Events\Security\OnBeforeAuthenticationEvent;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Interfaces\Security\PasswordAuthenticationTokenInterface;
use exface\Core\CommonLogic\Security\AuthenticationToken\UsernamePasswordAuthToken;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;

/**
 * This PSR-15 middleware to handle authentication via workbench security.
 * 
 * This middleware will fire an `exface.Core.Security.OnBeforeAuthentication` event
 * to allow custom authenticators listening to the event to authenticate the user.
 * 
 * Additionally, you can provide an array of callables to extract different types
 * of tokens from the request via the constructor argument `$tokenExtractors`.
 * Each token extractor must have the following signature:
 * 
 * ```
 *  function(
 *      Psr\Http\Message\ServerRequestInterface $request
 *      exface\Core\Interfaces\Facades\HttpFacadeInterface $facade
 *  ) : ?\exface\Core\Interfaces\Security\AuthenticationTokenInterface
 * ```
 * 
 * The middleware provides a built-in extractor via `extractBasicHttpAuthToken()` static method.
 * It is not being used implicitly though. To enable it, instatiate the middleware like this:
 * 
 * ```
 *  new AuthenticationMiddleware(
 *      $facade,
 *      [
 *          [AuthenticationMiddleware::class, 'extractBasicHttpAuthToken']
 *      ]
 *  )
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class AuthenticationMiddleware implements MiddlewareInterface
{
    private $workbench = null;
    
    private $facade = null;
    
    private $denyAnonymous = null;
    
    private $tokenExtractors = [];
    
    private $excludePaths = [];
    
    /**
     * 
     * @param HttpFacadeInterface $facade
     * @param callable[] $tokenExtractors
     * @param bool $denyAnonymous
     */
    public function __construct(HttpFacadeInterface $facade, array $tokenExtractors = [], bool $denyAnonymous = false)
    {
        $this->workbench = $facade->getWorkbench();
        $this->facade = $facade;
        $this->tokenExtractors = $tokenExtractors;
        $this->denyAnonymous = $denyAnonymous;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestPath = $request->getUri()->getPath();
        foreach ($this->excludePaths as $pattern) {
            if (preg_match($pattern, $requestPath) === 1) {
                return $handler->handle($request);
            }
        }
        // Fire OnBeforeAuthenticationEvent for custom authenticators listening to it
        $this->workbench->eventManager()->dispatch(new OnBeforeAuthenticationEvent($this->facade));
        // If any of the custom authenticators were successfull, we would get a non-anonymous token here
        $authenticatedToken = $this->workbench->getSecurity()->getAuthenticatedToken();
        
        // If the current token is still anonymous, try to get more tokens from the request
        // and authenticate them. The first one to authenticate successfully wins!
        if ($authenticatedToken->isAnonymous() === true) {
            $authTokens = $this->getAuthTokens($request);
            foreach ($authTokens as $token) {
                try {
                    $authenticatedToken = $this->workbench->getSecurity()->authenticate($token);
                    break;
                } catch (AuthenticationFailedError $e) {
                    // do nothing, try next authenticator
                    $this->workbench->getLogger()->logException($e, LoggerInterface::INFO);
                }
            }
        }
        
        // If the token is still anonymous, check if that is allowed in the configuration!
        if (true === $authenticatedToken->isAnonymous() && false === $this->isAnonymousAllowed()) {
            return $this->createResponseAccessDenied($request);
        }
        
        return $handler->handle($request);
    }
    
    protected function isAnonymousAllowed() : bool
    {
        return $this->denyAnonymous !== null ? $this->denyAnonymous === false : $this->workbench->getConfig()->getOption('SECURITY.DISABLE_ANONYMOUS_ACCESS') === false;
    }
    
    /**
     * 
     * @param string $content
     * @return ResponseInterface
     */
    protected function createResponseAccessDenied(ServerRequestInterface $request, string $content = null) : ResponseInterface
    {
        $content = $content ?? 'Anonymous access denied!';
        $exception = new AuthenticationFailedError($this->workbench->getSecurity(), $content);
        
        if ($this->facade instanceof AbstractAjaxFacade) {
            return $this->facade->createResponseFromError($request, $exception);
        } else {
            return new Response(403, [], $content);
        }
    }
    
    /**
     * 
     * @param ServerRequestInterface $request
     * @return array
     */
    protected function getAuthTokens(ServerRequestInterface $request) : array
    {
        $tokens = [];
        foreach ($this->tokenExtractors as $extractor) {
            if (is_callable($extractor) === false) {
                throw new FacadeLogicError('Invalid token extractor provided for the AuthenticationMiddleware: expecting a callable, received "' . gettype($extractor) . '"!');
            }
            $token = $extractor($request, $this->facade);
            if ($token !== null && ! ($token instanceof AuthenticationTokenInterface)) {
                throw new FacadeLogicError('Cannot use "' . gettype($token) . '" aus authentication token: token extractors are expected to produce instances of the AuthenticationTokenInterface!');
            }
            if ($token !== null) {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }
    
    /**
     * Token extractor for HTTP basic auth - produces a UsernamePasswordAuthToken.
     * 
     * Usage:
     * 
     * ```
     *  new AuthenticationMiddleware(
     *      $facade,
     *      [
     *          [AuthenticationMiddleware::class, 'extractBasicHttpAuthToken']
     *      ]
     *  )
     * ```
     * 
     * @param ServerRequestInterface $request
     * @return PasswordAuthenticationTokenInterface|NULL
     */
    public static function extractBasicHttpAuthToken(ServerRequestInterface $request, HttpFacadeInterface $facade) : ?UsernamePasswordAuthToken
    {
        $matches = [];
        if (preg_match("/Basic\s+(.*)$/i", $request->getHeaderLine("Authorization"), $matches)) {
            $explodedCredential = explode(":", base64_decode($matches[1]), 2);
            if (count($explodedCredential) == 2) {
                list($username, $password) = $explodedCredential;
                return new UsernamePasswordAuthToken($username, $password, $facade);
            }
        }
        return null;
    }
    
    public function addExcludePath(string $regex) : AuthenticationMiddleware
    {
        $this->excludePaths[] = $regex;
        return $this;
    }
}