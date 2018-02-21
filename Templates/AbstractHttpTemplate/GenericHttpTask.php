<?php
namespace exface\Core\Templates\AbstractHttpTemplate;

use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Interfaces\Templates\TemplateInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\CommonLogic\Tasks\GenericTask;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\CommonLogic\Selectors\MetaObjectSelector;
use GuzzleHttp\Psr7\ServerRequest;
use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\Interfaces\Tasks\TaskInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\HttpTaskInterface::getHttpRequest()
     */
    public function getHttpRequest() : ServerRequestInterface
    {
        return $this->request;
    }
    
    /**
     * 
     * @param ServerRequestInterface $request
     * @return HttpTaskInterface
     */
    protected function setRequest(ServerRequestInterface $request) : HttpTaskInterface
    {
        $this->request = $request;
        $this->importRequestParameters($request);        
        return $this;
    }
    
    /**
     * 
     * @param ServerRequestInterface $request
     * @return HttpTaskInterface
     */
    protected function importRequestParameters(ServerRequestInterface $request) : HttpTaskInterface
    {
        $requestParams = $request->getQueryParams();
        if (is_array($request->getParsedBody()) || $request->getParsedBody()) {
            $requestParams = array_merge($requestParams, $request->getParsedBody());
        }
        $this->setParameters($requestParams);
        return $this;
    }
    
    /**
     * 
     * @param ServerRequestInterface $request
     * @return HttpTaskInterface
     */
    public function setParameter($name, $value) : TaskInterface
    {
        $name = strtolower($name);
        
        parent::setParameter($name, $value);
        
        switch ($name) {
            case $this->getParamNameAction() :
                $this->setActionSelector(new ActionSelector($this->getWorkbench(), $value));
                break;
            case $this->getParamNameObject() : 
                $this->setMetaObjectSelector(new MetaObjectSelector($this->getWorkbench(), $value));
                break;
            case $this->getParamNamePage() :
                $this->setOriginPageSelector(new UiPageSelector($this->getWorkbench(), $value));
                break;
            case $this->getParamNameWidget() :
                $this->setOriginWidgetId($value);
                break;
        }
        
        return $this;
    }
    
    /**
     * Returns the name of the URL parameter holding the action selector or NULL
     * if no such URL parameter exists (e.g. the action is derived from the path).
     * 
     * @return string|null
     */
    protected function getParamNameAction()
    {
        return 'action';
    }
    
    /**
     * Returns the name of the URL parameter holding the object selector or NULL
     * if no such URL parameter exists (e.g. the object is derived from the path).
     *
     * @return string|null
     */
    protected function getParamNameObject()
    {
        return 'object';
    }
    
    /**
     * Returns the name of the URL parameter holding the page selector or NULL
     * if no such URL parameter exists (e.g. the page is derived from the path).
     *
     * @return string|null
     */
    protected function getParamNamePage()
    {
        return 'resource';
    }
    
    /**
     * Returns the name of the URL parameter holding the widget selector or NULL
     * if no such URL parameter exists (e.g. the widget is derived from the path).
     *
     * @return string|null
     */
    protected function getParamNameWidget()
    {
        return 'element';
    }
}