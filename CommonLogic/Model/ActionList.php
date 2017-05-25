<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\EntityList;
use exface\Core\Interfaces\Actions\ActionInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 * @method ActionInterface[] getAll()
 * @method ActionList|ActionInterface[] getIterator()
 * @method ActionInterface get()
 * @method ActionInterface getFirst()
 * @method ActionInterface getLast()
 * @method ActionList copy()
 *        
 */
class ActionList extends EntityList
{

    /**
     * An action list stores actions with their aliases for keys unless the keys are explicitly specified.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\EntityList::add()
     * @param ActionInterface $action            
     */
    public function add($action, $key = null)
    {
        if (is_null($key)) {
            $key = $action->getAliasWithNamespace();
        }
        return parent::add($action, $key);
    }
}