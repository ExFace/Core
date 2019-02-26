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
     * @see \exface\Core\Uxon\UxonSchema::getEntityClass()
     */
    public function getEntityClass(UxonObject $uxon, array $path, string $rootPrototypeClass = '\exface\Core\CommonLogic\AbstractAction') : string
    {
        $name = $rootPrototypeClass;
        
        foreach ($uxon as $key => $value) {
            if (strcasecmp($key, 'alias') === 0) {
                $w = $this->getEntityClassFromSelector($value);
                if ($this->validateEntityClass($w) === true) {
                    $name = $w;
                }
            }
        }
        
        if (count($path) > 1) {
            return parent::getEntityClass($uxon, $path, $name);
        }
        
        return $name;
    }
    
    /**
     * Returns the entity class for a given action selector (e.g. alias).
     *
     * @param string $selectorString
     * @return string
     */
    protected function getEntityClassFromSelector(string $selectorString) : string
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