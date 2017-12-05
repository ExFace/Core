<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\EntityList;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\ActionListInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 * @method ActionInterface[] getAll()
 * @method ActionListInterface|ActionInterface[] getIterator()
 * @method ActionInterface get()
 * @method ActionInterface getFirst()
 * @method ActionInterface getLast()
 * @method ActionListInterface copy()
 *        
 */
class ActionList extends EntityList implements ActionListInterface
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