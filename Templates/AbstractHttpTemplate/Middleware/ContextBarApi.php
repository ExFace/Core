<?php
namespace exface\Core\Templates\AbstractHttpTemplate\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Templates\AbstractAjaxTemplate\AbstractAjaxTemplate;
use GuzzleHttp\Psr7\Response;

/**
 * This PSR-15 middleware handles context bar update requests in templates
 * based on the AbstractAjaxTemplate.
 * 
 * It handles the in-template route [page_selector]/context.
 * 
 * @author Andrej Kabachnik
 *
 */
class ContextBarApi implements MiddlewareInterface
{
    private $template = null;
    
    private $contextRoute = '';
    
    /**
     * 
     * @param AbstractAjaxTemplate $template
     * @param string $readUrlParam
     * @param string $passToMethod
     * @param string $taskAttributeName
     */
    public function __construct(AbstractAjaxTemplate $template, string $contextRoute = '/context')
    {
        $this->template = $template;
        $this->contextRoute = $contextRoute;
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
            $page = UiPageFactory::createFromCmsPage($this->template->getWorkbench()->getCMS(), $pageAlias);
            $json = $this->template->getElement($page->getContextBar())->buildJsonContextBarUpdate();
            return new Response(200, [], $this->template->encodeData($json));
        }
        
        return $handler->handle($request);
    }
    
    
}