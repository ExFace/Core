<?php
namespace exface\Core\Templates\AbstractHttpTemplate;

use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Interfaces\Templates\TemplateInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\CommonLogic\GenericTask;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\CommonLogic\Selectors\MetaObjectSelector;
use GuzzleHttp\Psr7\ServerRequest;
use exface\Core\CommonLogic\Selectors\UiPageSelector;

class GenericHttpTask extends GenericTask implements HttpTaskInterface
{
    private $request = null;
    
    /**
     * 
     * @param TemplateInterface $template
     * @param ServerRequestInterface $request
     */
    public function __construct(TemplateInterface $template, ServerRequestInterface $request = null)
    {
        parent::__construct($template);
        if (is_null($request)) {
            $request = ServerRequest::fromGlobals();
        }
        $this->setRequest($request);
    }
    
    /**
     * 
     * @param ServerRequestInterface $request
     * @return HttpTaskInterface
     */
    protected function setRequest(ServerRequestInterface $request) : HttpTaskInterface
    {
        $this->request = $request;
        
        $requestParams = $request->getQueryParams();
        if (is_array($request->getParsedBody()) || $request->getParsedBody()) {
            $requestParams = array_merge($requestParams, $request->getParsedBody());
        }
        
        $this->setParameters($requestParams);
        
        if ($this->hasParameter('action')) {
            $this->setActionSelector(new ActionSelector($this->getWorkbench(), $this->getParameter('action')));
        }
        
        if ($this->hasParameter('object')) {
            $this->setMetaObjectSelector(new MetaObjectSelector($this->getWorkbench(), $this->getParameter('object')));
        }
        
        if ($this->hasParameter('resource')) {
            $this->setOriginPageSelector(new UiPageSelector($this->getWorkbench(), $this->getParameter('resource')));
        }
        
        if ($this->hasParameter('element')) {
            $this->setOriginWidgetId($this->getParameter('element'));
        }
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\HttpTaskInterface::getHttpRequest()
     */
    public function getHttpRequest() : ServerRequestInterface
    {
        return $this->request;
    }
}