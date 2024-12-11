<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\ServerRequest;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class HttpTask extends GenericTask implements HttpTaskInterface
{
    const REQUEST_ATTRIBUTE_NAME_ACTION = "action";

    const REQUEST_ATTRIBUTE_NAME_PAGE = "page"; 

    const REQUEST_ATTRIBUTE_NAME_WIDGET = "widget";

    private $request = null;
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param ServerRequestInterface $request
     */
    public function __construct(WorkbenchInterface $workbench, FacadeInterface $facade = null, ServerRequestInterface $request = null)
    {
        parent::__construct($workbench, $facade);
        if (null === $request) {
            $request = ServerRequest::fromGlobals();
        } else {
            // See if certain parameters were already resolved and stored in request attributes:
            // e.g. in FacadeResolverMiddleware
            if (null !== $val = $request->getAttribute(self::REQUEST_ATTRIBUTE_NAME_ACTION)) {
                $this->setActionSelector($val);
            }
            if (null !== $val = $request->getAttribute(self::REQUEST_ATTRIBUTE_NAME_PAGE)) {
                $this->setPageSelector($val);
            }
            if (null !== $val = $request->getAttribute(self::REQUEST_ATTRIBUTE_NAME_WIDGET)) {
                $this->setWidgetIdTriggeredBy($val);
            }
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
}