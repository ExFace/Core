<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use GuzzleHttp\Psr7\Response;

/**
 * This PSR-15 middleware handles context bar update requests in facades
 * based on the AbstractAjaxFacade.
 * 
 * It handles the in-facade route [page_selector]/context.
 * 
 * @author Andrej Kabachnik
 *
 */
class ContextBarApi implements MiddlewareInterface
{
    private $facade = null;
    
    private $contextRoute = '';
    
    private $responseHeaders = [];
    
    /**
     * 
     * @param AbstractAjaxFacade $facade
     * @param string $readUrlParam
     * @param string $passToMethod
     * @param string $taskAttributeName
     */
    public function __construct(AbstractAjaxFacade $facade, array $responseHeader = [], string $contextRoute = '/context')
    {
        $this->facade = $facade;
        $this->contextRoute = $contextRoute;
        $this->responseHeaders = $responseHeader;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri()->__toString();
        if (StringDataType::endsWith($uri, $this->contextRoute)) {
            $uriParts = explode('/', $uri);
            $pageAlias = $uriParts[(count($uriParts) - 2)];
            if ($pageAlias) {
                $page = UiPageFactory::createFromModel($this->facade->getWorkbench(), $pageAlias);
            } else {
                $page = UiPageFactory::createEmpty($this->facade->getWorkbench());
            }
            $json = $this->facade->getElement($page->getContextBar())->buildJsonContextBarUpdate();
            $headers = $this->responseHeaders;
            $headers['Content-Type'] = 'application/json';
            return new Response(200, $headers, $this->facade->encodeData($json));
        }
        
        return $handler->handle($request);
    }
    
    
}