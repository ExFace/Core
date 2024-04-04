<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Contexts\FilterContext;
use exface\Core\Contexts\ActionContext;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\ContextFactory;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Events\Contexts\OnContextInitEvent;
use exface\Core\Interfaces\Selectors\ContextSelectorInterface;
use exface\Core\Exceptions\Contexts\ContextAccessDeniedError;
use exface\Core\Interfaces\Log\LoggerInterface;

abstract class AbstractContextScope implements ContextScopeInterface
{
    private $active_contexts = array();
    
    private $active_errors = array();

    private $exface = NULL;

    private $name = null;

    public function __construct(Workbench $exface)
    {
        $this->exface = $exface;
        $this->name = str_replace('ContextScope', '', substr(get_class($this), (strrpos(get_class($this), '\\') + 1)));
    }

    /**
     * Performs all neccessary logic to get the context scope up and running.
     * This may be connecting to DBs,
     * reading files, preparing data structures, etc. This method is called right after each context scope is
     * created.
     *
     * @return AbstractContextScope
     */
    public function init()
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
    public function getContext($aliasOrSelector) : ContextInterface
    {
        // If no context matching the alias exists, try to create one
        $cache = $this->active_contexts[(string)$aliasOrSelector] ?? null;
        
        if ($cache === null) {
            if (null !== $err = $this->active_errors[(string)$aliasOrSelector] ?? null) {
                throw $err;
            }
            try {
                if ($aliasOrSelector instanceof ContextSelectorInterface) {
                    $selector = $aliasOrSelector;
                } else {
                    $selector = SelectorFactory::createContextSelector($this->getWorkbench(), $aliasOrSelector);  
                }
                $context = ContextFactory::createInScope($selector, $this);
                // If the selector was not an alias, see if the cache already has 
                if ($selector->isAlias() === false && $this->active_contexts[$context->getAliasWithNamespace()] !== null) {
                    $instance = $this->active_contexts[$context->getAliasWithNamespace()];
                    unset($context);
                    return $instance;
                }
                $this->getWorkbench()->eventManager()->dispatch(new OnContextInitEvent($context));
                $this->active_contexts[$context->getAliasWithNamespace()] = $context;
                $this->active_contexts[$selector->toString()] = $context;
                $this->loadContextData($context);
                return $context;
            } catch (\Throwable $e) {
                $this->active_errors[$context->getAliasWithNamespace()] = $e;
                $this->active_errors[(string)$aliasOrSelector] = $e;
                throw $e;
            }
        }
        
        return $cache;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::hasContext()
     */
    public function hasContext($aliasOrSelector, bool $loadContextIfPossible = true) : bool
    {
        $cache = ($this->active_contexts[(string)$aliasOrSelector] ?? null);
        if ($cache !== null) {
            return true;
        }
        
        $cache = ($this->active_errors[(string)$aliasOrSelector] ?? null);
        if ($cache !== null) {
            return false;
        }
        
        if ($loadContextIfPossible === true) {
            try {
                $this->getContext($aliasOrSelector);
                return true;
            } catch (ContextAccessDeniedError $e) {
                $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::DEBUG);
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e);
            }
        }
        
        return false;
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::reloadContext()
     */
    public function reloadContext(ContextInterface $context)
    {
        $this->loadContextData($context);
        return;
    }

    /**
     * Loads data saved in the current context scope into the given context object
     *
     * @return AbstractContextScope
     */
    abstract protected function loadContextData(ContextInterface $context);

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::saveContexts()
     */
    abstract public function saveContexts();

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
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
        return $this->getWorkbench()->getContext();
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