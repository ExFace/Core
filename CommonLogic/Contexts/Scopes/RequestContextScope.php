<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextInterface;

class RequestContextScope extends AbstractContextScope
{
    
    const SUBREQUEST_SEPARATOR = ':';

    /**
     * Unique id of the request, that is being handled by this instance
     * @var string $request_id
     */
    private $main_request_id = null;
    
    /**
     * Id of the current subrequest if the request has multiple subrequests
     * @var string $subrequest_id
     */
    private $subrequest_id = null;

    /**
     * There is nothing to load in the request context scope, as it only lives for one request
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::load_contexts()
     */
    public function loadContextData(ContextInterface $context)
    {}

    /**
     * The request context scope does not need to be saved, as it only lives for one request
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::saveContexts()
     */
    public function saveContexts()
    {}
    
    /**
     * Returns the unique id of this request.
     * 
     * The request id can either be sent by the client using the x-request-id
     * header or will be generated automatically. The request id can be
     * optionally extended by a subrequest id (separated by the SUBREQUEST_SEPARATOR)
     * to enable bundling of AJAX-requests with the actual user request.
     * 
     * Each template is free to deal with request ids and x-request-id headers 
     * in it's own way. If no request id is set by the template each request
     * will get it's own id (no subrequests!). If identifying subrequests is 
     * desired, templates must set either a request id with a subrequest part
     * or the subrequest id explicitly.
     * 
     * While using subrequest ids is advisable for AJAX-templates, API-templates
     * are encouraged to use the x-request-id header to pass an external
     * request id. This will get logged with every log message, so logs for
     * specific requests made by external systems can be easily found.
     * 
     * @see getSubrequestId()
     * 
     * @return string
     */
    public function getRequestId()
    {
        if (is_null($this->main_request_id)){
            $this->main_request_id = $this::generateRequestId();
        }
        return $this->main_request_id. ($this->getSubrequestId() ? self::SUBREQUEST_SEPARATOR . $this->getSubrequestId() : '');
    }
    
    /**
     * 
     * @return string
     */
    public static function generateRequestId() : string
    {
        return md5(uniqid(rand(), true));
    }
    
    /**
     * Sets the request id for the current request. If it contains the 
     * SUBREQUEST_SEPARATOR, everything following it will be considered to be
     * the subrequest id.
     * 
     * @see getRequestId() for explanations.
     * 
     * @param string $value
     * @return \exface\Core\CommonLogic\Contexts\Scopes\RequestContextScope
     */
    public function setRequestId($value)
    {
        $ids = explode(self::SUBREQUEST_SEPARATOR, $value);
        $this->main_request_id = $ids[0];
        $this->setSubrequestId($ids[1]);
        return $this;
    }
    
    /**
     * Returns the subrequest id without the rest of the request id. Returns 
     * NULL if there is no subrequest id.
     * 
     * @see getRequestId() for explanations.
     * 
     * @return string|null
     */
    public function getSubrequestId()
    {
        return $this->subrequest_id;
    }
    
    /**
     * Sets the subrequest id. It will be appended to the existing (or autogenerated) request id.
     * 
     * @see getRequestId() for explanations.
     * 
     * @param string $value
     * @return \exface\Core\CommonLogic\Contexts\Scopes\RequestContextScope
     */
    public function setSubrequestId($value)
    {
        $this->subrequest_id = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::getScopeId()
     */
    public function getScopeId()
    {
        return $this->getRequestId();
    }
}
?>