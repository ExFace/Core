<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Actions\ActionInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface ActionListInterface extends EntityListInterface
{
    
    /**
     * An action list stores actions with their aliases for keys unless the keys are explicitly specified.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\EntityList::add()
     * @param ActionInterface $action
     */
    public function add($action, $key = null);
}
?>