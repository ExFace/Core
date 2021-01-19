<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;

class ApplicationContextScope extends AbstractContextScope
{
    private $vars = [];

    /**
     * TODO
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::load_contexts()
     */
    public function loadContextData(ContextInterface $context)
    {}

    /**
     * TODO
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::saveContexts()
     */
    public function saveContexts()
    {}
    
    /**
     *
     * @param string $name
     * @param mixed $value
     * @param string $namespace
     * @return ContextScopeInterface
     */
    public function setVariable(string $name, $value, string $namespace = null) : ContextScopeInterface
    {
        $this->vars[($namespace !== null ? $namespace . '_' : '') . $name] =  $value;
        return $this;
    }
    
    /**
     *
     * @param string $name
     * @param string $namespace
     * @return ContextScopeInterface
     */
    public function unsetVariable(string $name, string $namespace = null) : ContextScopeInterface
    {
        unset($this->vars[($namespace !== null ? $namespace . '_' : '') . $name]);
        return $this;
    }
    
    /**
     *
     * @param string $name
     * @param string $namespace
     * @return mixed
     */
    public function getVariable(string $name, string $namespace = null)
    {
        return $this->vars[($namespace !== null ? $namespace . '_' : '') . $name];
    }
}