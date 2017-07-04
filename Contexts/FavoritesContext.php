<?php
namespace exface\Core\Contexts;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class FavoritesContext extends ObjectBasketContext
{

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
    
    public function getIcon()
    {
        return 'star';
    }
    
    public function getName()
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.FAVORITES.NAME');
    }
    
}
?>