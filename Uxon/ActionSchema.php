<?php
namespace exface\Core\Uxon;

use exface\Core\Factories\SelectorFactory;
use exface\Core\Factories\ActionFactory;
use exface\Core\CommonLogic\UxonObject;

/**
 * UXON-schema class for actions.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class ActionSchema extends UxonSchema
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPrototypeClass()
     */
    public function getPrototypeClass(UxonObject $uxon, array $path, string $rootPrototypeClass = '\exface\Core\CommonLogic\AbstractAction') : string
    {
        $name = $rootPrototypeClass;
        
        foreach ($uxon as $key => $value) {
            if (strcasecmp($key, 'alias') === 0) {
                $w = $this->getPrototypeClassFromSelector($value);
                if ($this->validatePrototypeClass($w) === true) {
                    $name = $w;
                }
                break;
            }
        }
        
        if (count($path) > 1) {
            return parent::getPrototypeClass($uxon, $path, $name);
        }
        
        return $name;
    }
    
    /**
     * Returns the prototype class for a given action selector (e.g. alias).
     *
     * @param string $selectorString
     * @return string
     */
    protected function getPrototypeClassFromSelector(string $selectorString) : string
    {
        try {
            $selector = SelectorFactory::createActionSelector($this->getWorkbench(), $selectorString);
            $action = ActionFactory::create($selector);
        } catch (\Throwable $e) {
            return '\exface\Core\CommonLogic\AbstractAction';
        }
        return get_class($action);
    }
}