<?php
namespace exface\Core\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Contexts\Types\FilterContext;
use exface\Core\Contexts\Types\ActionContext;
use exface\Core\Contexts\Types\AbstractContext;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\Contexts\ContextNotFoundError;

abstract class AbstractContextScope implements ContextScopeInterface
{

    private $active_contexts = array();

    private $exface = NULL;

    private $name = null;

    public function __construct(Workbench $exface)
    {
        $this->exface = $exface;
        $this->init();
        $this->name = substr(get_class($this), (strrpos(get_class($this), '\\') + 1));
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
        return $this->getContext('Filter');
    }

    /**
     * Returns the action context of the current scope.
     * Shortcut for calling get_context ('action')
     *
     * @return ActionContext
     */
    public function getActionContext()
    {
        return $this->getContext('Action');
    }

    /**
     * Returns an array with all contexts available in this scope.
     *
     * @return AbstractContext[]
     */
    public function getAllContexts()
    {
        return $this->active_contexts;
    }

    /**
     * Returns the context matching the given alias (like "action", "filter", "test", etc.).
     * If the context
     * is not initialized yet, it will be initialized now and saved contexts will be loaded.
     *
     * @param string $alias            
     * @return AbstractContext
     */
    public function getContext($alias)
    {
        // If no context matching the alias exists, try to create one
        if (! $this->active_contexts[$alias]) {
            $context_class = $this->getClassFromAlias($alias);
            if (class_exists($context_class)) {
                $context = new $context_class($this->exface);
                $context->setScope($this);
                $this->loadContextData($context);
                $this->active_contexts[$alias] = $context;
            } else {
                throw new ContextNotFoundError('Cannot create context "' . $alias . '": class "' . $context_class . '" not found!', '6T5E24E');
            }
        }
        return $this->active_contexts[$alias];
    }

    protected function getClassFromAlias($context_alias)
    {
        $context_class = '\\exface\\Core\\Contexts\\Types\\' . $context_alias . 'Context';
        if (! class_exists($context_class)) {
            $context_class = '\\exface\\Core\\Contexts\\Types\\' . ucfirst(strtolower($context_alias)) . 'Context';
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
     * Saves data of all contexts in the current scope to the scopes storage
     *
     * @return AbstractContextScope
     */
    abstract public function saveContexts();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::getContextManager()
     */
    public function getContextManager()
    {
        return $this->getWorkbench()->context();
    }

    /**
     * Returns a unique identifier of the context scope: e.g.
     * the session id for window or session context, the user id
     * for user context, the app alias for app contexts, etc. This id is mainly used as a key for storing information from
     * the context (see session scope example).
     *
     * @return string
     */
    public function getScopeId()
    {
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::getName()
     */
    public function getName()
    {
        return $this->name;
    }
}
?>