<?php

namespace exface\Core\Actions;

/**
 * Adds instances from the input data to the favorites basket of the current user.
 *
 * This action is similar to ObjectBasketRemove except that it removes items from Favorites (= object basket in
 * the user context scope).
 *
 * @author Andrej Kabachnik
 *        
 */
class FavoritesRemove extends ObjectBasketRemove
{

    protected function init()
    {
        parent::init();
        $this->setIconName('star');
    }

    /**
     * In constrast to the generic object basket, favorites are always stored in the user context scope.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Actions\ObjectBasketAdd::getScope()
     */
    public function getScope()
    {
        $this->setScope('User');
        return parent::getScope();
    }
}
?>