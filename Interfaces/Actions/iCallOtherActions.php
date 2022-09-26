<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

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
     * @return bool
     */
    public function getUseSingleTransaction() : bool;
    
    /**
     * 
     * @param string $classOrInterface
     * @param bool $onlyThisClass
     * @return bool
     */
    public function containsActionClass(string $classOrInterface, bool $onlyThisClass = false) : bool;
    
    /**
     * 
     * @param ActionSelectorInterface|string $actionOrSelectorOrString
     * @return bool
     */
    public function containsAction($actionOrSelectorOrString) : bool;
    
    /**
     * Returns the first action, that needs to be run for the given task.
     * 
     * Complex workflows or action chains may start differently depending on the task information (e.g.
     * base object of the task, or input data). This method returns the first action to be performed
     * which is important for facades, that need to the resulting action type.
     * 
     * @param TaskInterface $task
     * @return ActionInterface|NULL
     */
    public function getActionToStart(TaskInterface $task) : ?ActionInterface;
}