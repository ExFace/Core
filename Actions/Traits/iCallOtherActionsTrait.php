<?php
namespace exface\Core\Actions\Traits;

use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iCallOtherActions;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
trait iCallOtherActionsTrait 
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::getActionsRecursive()
     */
    public function getActionsRecursive(callable $filter = null) : array
    {
        $found = [];
        foreach ($this->getActions() as $chainedAction) {
            if ($filter === null || $filter($chainedAction) === true) {
                $found[] = $chainedAction;
            }
            if ($chainedAction instanceof iCallOtherActions) {
                $found = array_merge($found, $chainedAction->getActionsRecursive($filter));
            }
        }
        return $found;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::containsAction()
     */
    public function containsAction(ActionInterface $action, bool $recursive = true): bool
    {
        foreach ($this->getActions() as $chainedAction) {
            if ($chainedAction === $action) {
                return true;
            }
            if ($recursive === true && $chainedAction instanceof iCallOtherActions) {
                if ($chainedAction->containsAction($action, $recursive) === true) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::containsActionSelector()
     */
    public function containsActionSelector($actionOrSelectorOrString, bool $recursive = true): bool
    {
        if (is_string($actionOrSelectorOrString)) {
            $selector = new ActionSelector($this->getWorkbench(), $actionOrSelectorOrString);
        } else {
            $selector = $actionOrSelectorOrString;
        }
        
        foreach ($this->getActions() as $chainedAction) {
            if ($chainedAction->is($selector)) {
                return true;
            }
            if ($recursive === true && $chainedAction instanceof iCallOtherActions) {
                if ($chainedAction->containsActionSelector($selector, $recursive) === true) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::containsActionClass()
     */
    public function containsActionClass(string $classOrInterface, bool $recursive = true, bool $onlyThisClass = false): bool
    {
        $classOrInterface = '\\' . ltrim(trim($classOrInterface), '\\');
        $contains = false;
        foreach ($this->getActions() as $chainedAction) {
            if (is_a($chainedAction, $classOrInterface, true) === true) {
                $contains = true;
            } else {
                $contains = false;
            }
            if ($contains === false && $recursive === true && $chainedAction instanceof iCallOtherActions) {
                $contains = $chainedAction->containsActionClass($classOrInterface, $recursive, $onlyThisClass);
            }
            if($onlyThisClass === false && $contains === true) {
                return true;
            }
            if($onlyThisClass === true && $contains === false) {
                return false;
            }
        }
        return $contains;
    }
}