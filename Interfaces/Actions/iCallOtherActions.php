<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\CommonLogic\UxonObject;

/**
 * Interface for actions, that call other actions (e.g. chaines, workflows, etc.)
 *
 * @author Andrej Kabachnik
 *        
 */
interface iCallOtherActions extends ActionInterface
{
    /**
     *
     * @return ActionInterface[]
     */
    public function getActions() : array;
    
    /**
     * 
     * @param UxonObject|ActionInterface[] $uxon_array_or_action_list
     * @return iCallOtherActions
     */
    public function setActions($uxon_array_or_action_list) : iCallOtherActions;
    
    /**
     * 
     * @param ActionInterface $action
     * @return iCallOtherActions
     */
    public function addAction(ActionInterface $action) : iCallOtherActions;
    
    /**
     * 
     * @return bool
     */
    public function getUseSingleTransaction() : bool;
    
    /**
     * 
     * @param bool $value
     * @return iCallOtherActions
     */
    public function setUseSingleTransaction(bool $value) : iCallOtherActions;
}