<?php
namespace exface\Core\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextInterface;

class RequestContextScope extends AbstractContextScope
{

    /**
     * Unique id of the request, that is being handled by this instance - may be set set by the template when calling the server
     */
    private $request_id = null;

    /**
     * There is nothing to load in the request context scope, as it only lives for one request
     *
     * @see \exface\Core\Contexts\Scopes\AbstractContextScope::load_contexts()
     */
    public function loadContextData(ContextInterface $context)
    {}

    /**
     * The request context scope does not need to be saved, as it only lives for one request
     *
     * @see \exface\Core\Contexts\Scopes\AbstractContextScope::saveContexts()
     */
    public function saveContexts()
    {}

    public function getRequetsId()
    {
        return $this->requets_id;
    }

    public function setRequetsId($value)
    {
        $this->requets_id = $value;
        return $this;
    }

    public function getScopeId()
    {
        return $this->getRequetsId();
    }
}
?>