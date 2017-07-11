<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Contexts\FilterContext;
use exface\Core\Contexts\ActionContext;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\Contexts\ContextNotFoundError;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Factories\ContextFactory;

abstract class AbstractContextScope implements ContextScopeInterface
{

    private $active_contexts = array();

    private $exface = NULL;

    private $name = null;

    public function __construct(Workbench $exface)
    {
        $this->exface = $exface;
        $this->name = str_replace('ContextScope', '', substr(get_class($this), (strrpos(get_class($this), '\\') + 1)));
        $this->init();
    }

    /**
     * Performs all neccessary logic to get the context scope up and running.
     * This may be connecting to DBs,
     * reading files, preparing data structures, etc. This method is called right after each context scope is
     * created.
     *
     * @return AbstractContextScope
     */
    protected function init()
    {
        return $this;
    }

    /**
     * Returns the filter context of the current scope.
     * Shortcut for calling get_context('filter')
     *
     * @return FilterContext
     */
    public function getFilterContext()
    {
        return $this->getContext('exface.Core.FilterContext');
    }

    /**
     * Returns the action context of the current scope.
     * Shortcut for calling get_context ('action')
     *
     * @return ActionContext
     */
    public function getActionContext()
    {
        return $this->getContext('exface.Core.ActionContext');
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::getContextsLoaded()
     */
    public function getContextsLoaded()
    {
        return $this->active_contexts;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::getContext()
     */
    public function getContext($alias)
    {
        // If no context matching the alias exists, try to create one
        if (! $this->active_contexts[$alias]) {
            $name_resolver = NameResolver::createFromString($alias, NameResolver::OBJECT_TYPE_CONTEXT, $this->getWorkbench());            
            if ($name_resolver->classExists()){
                $context = ContextFactory::createInScope($name_resolver, $this);
                $this->loadContextData($context);
                $this->active_contexts[$alias] = $context;
            } else {
                throw new ContextNotFoundError('Cannot create context "' . $alias . '": class "' . $name_resolver->getClassNameWithNamespace() . '" not found!', '6T5E24E');
            }
        }
        return $this->active_contexts[$alias];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::removeContext()
     */
    public function removeContext($alias)
    {
        unset($this->active_contexts[$alias]);
        return $this;
    }
    
    /**
     * 
     * @param string $context_alias
     * @return string
     */
    protected function getClassFromAlias($context_alias)
    {
        $context_class = '\\exface\\Core\\Contexts\\' . $context_alias . 'Context';
        if (! class_exists($context_class)) {
            $context_class = '\\exface\\Core\\Contexts\\' . ucfirst(strtolower($context_alias)) . 'Context';
        }
        return $context_class;
    }

    /**
     * Loads data saved in the current context scope into the given context object
     *
     * @return AbstractContextScope
     */
    abstract public function loadContextData(ContextInterface $context);

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::saveContexts()
     */
    abstract public function saveContexts();

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::getContextManager()
     */
    public function getContextManager()
    {
        return $this->getWorkbench()->context();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::getScopeId()
     */
    public function getScopeId()
    {
        return;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::getName()
     */
    public function getName()
    {
        return $this->name;
    }
}
?>