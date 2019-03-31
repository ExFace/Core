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
    private $request = null;
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param ServerRequestInterface $request
     */
    public function __construct(WorkbenchInterface $workbench, FacadeInterface $facade = null, ServerRequestInterface $request = null)
    {
        parent::__construct($workbench, $facade);
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
}