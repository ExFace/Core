<?php
namespace exface\Core\Actions\Traits;

use exface\Core\CommonLogic\Selectors\ActionSelector;

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
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::containsAction()
     */
    public function containsAction($actionOrSelectorOrString): bool
    {
        if (is_string($actionOrSelectorOrString)) {
            $actionOrSelectorOrString = new ActionSelector($this->getWorkbench(), $actionOrSelectorOrString);
        }
        
        foreach ($this->getActions() as $action) {
            if ($action->is($actionOrSelectorOrString)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallOtherActions::containsActionClass()
     */
    public function containsActionClass(string $classOrInterface, bool $onlyThisClass = false): bool
    {
        $classOrInterface = '\\' . ltrim(trim($classOrInterface), '\\');
        $contains = false;
        foreach ($this->getActions() as $action) {
            if (is_a($action, $classOrInterface, true) === true) {
                $contains = true;
            } else {
                $contains = false;
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