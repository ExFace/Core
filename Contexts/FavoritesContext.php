<?php
namespace exface\Core\Contexts;

use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Exceptions\Contexts\ContextRuntimeError;
use exface\Core\Exceptions\Contexts\ContextAccessDeniedError;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class FavoritesContext extends ObjectBasketContext
{
    public function __construct(NameResolverInterface $name_resolver){
        parent::__construct($name_resolver);
        if ($name_resolver->getWorkbench()->context()->getScopeUser()->isUserAnonymous()){
            throw new ContextAccessDeniedError($this, 'The favorites context cannot be used for anonymous users!');
        }
    }

    /**
     * The favorites context resides in the user scope.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Contexts\ObjectBasketContext::getDefaultScope()
     */
    public function getDefaultScope()
    {
        return $this->getWorkbench()->context()->getScopeUser();
    }
    
    public function getScope()
    {
        return $this->getDefaultScope();
    }
    
    public function setScope(ContextScopeInterface $context_scope)
    {
        if ($context_scope != $this->getDefaultScope()){
            throw new ContextRuntimeError($this, 'Cannot use context scope "' . $context_scope->getName() . '" for context "' . $this->getAliasWithNamespace() . '": only user context scope allowed!');
        }
        return parent::setScope($context_scope);
    }
    
    public function getIcon()
    {
        return Icons::STAR;
    }
    
    public function getName()
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.FAVORITES.NAME');
    }
    
}
?>