<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;

/**
 * Adds instances from the input data to the favorites basket of the current user.
 *
 * This action is similar to ObjectBasketAdd except that it saves things for longer use. Favorites are attached to a
 * specific user and are the same in all windows/sessions of this user. They get restored once the user logs on.
 *
 * @author Andrej Kabachnik
 *        
 */
class FavoritesAdd extends ObjectBasketAdd
{

    protected function init()
    {
        parent::init();
        $this->setIconName(Icons::STAR);
    }
    
    /**
     * The context type for the favorites-actions is allways FavoritesContext
     *
     * {@inheritdoc}
     * @see \exface\Core\Actions\ObjectBasketAdd::getContextScope()
     */
    public function getContextAlias()
    {
        $this->setContextAlias('exface.Core.FavoritesContext');
        return parent::getContextAlias();
    }

    /**
     * In constrast to the generic object basket, favorites are always stored in the user context scope.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Actions\ObjectBasketAdd::getContextScope()
     */
    public function getContextScope()
    {
        $this->setContextScope(ContextManagerInterface::CONTEXT_SCOPE_USER);
        return parent::getContextScope();
    }
}
?>