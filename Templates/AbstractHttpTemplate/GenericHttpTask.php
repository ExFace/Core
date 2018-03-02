<?php
namespace exface\Core\Templates\AbstractHttpTemplate;

use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Interfaces\Templates\TemplateInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\CommonLogic\Tasks\GenericTask;
use GuzzleHttp\Psr7\ServerRequest;
use exface\Core\Exceptions\InvalidArgumentException;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class GenericHttpTask extends GenericTask implements HttpTaskInterface
{
    private $request = null;
    
    private $mode = '';
    
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\HttpTaskInterface::getRenderingMode()
     */
    public function getRenderingMode()
    {
        return $this->mode;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\HttpTaskInterface::setRenderingMode()
     */
    public function setRenderingMode(string $bodyOrHead): HttpTaskInterface
    {
        $this->mode = $bodyOrHead;
        return $this;
    }
}