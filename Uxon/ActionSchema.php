<?php
namespace exface\Core\Uxon;

use exface\Core\Factories\SelectorFactory;
use exface\Core\Factories\ActionFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\DataTypes\UxonSchemaNameDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Log\LoggerInterface;

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
    public static function getSchemaName() : string
    {
        return UxonSchemaNameDataType::ACTION;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPrototypeClass()
     */
    public function getPrototypeClass(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : string
    {
        $name = $rootPrototypeClass ?? $this->getDefaultPrototypeClass();
        
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
            $ex = new RuntimeException('Error loading action autosuggest - falling back to "AbstractAction"!', null, $e);
            $this->getWorkbench()->getLogger()->logException($ex, LoggerInterface::DEBUG);
            return $this->getDefaultPrototypeClass();
        }
        return get_class($action);
    }
    
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractAction::class;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPropertyValueRecursive()
     */
    public function getPropertyValueRecursive(UxonObject $uxon, array $path, string $propertyName, string $rootValue = '', string $prototypeClass = null)
    {
        if ($propertyName === 'object_alias' && $path[0] === 'input_mappers' && $path[count($path)-1] === 'from') {
            $mapper = $uxon->getProperty($path[0])->getProperty($path[1]);
            if ($mapper->hasProperty('from_object_alias')) {
                return $mapper->getProperty('from_object_alias');
            }
        }
        return parent::getPropertyValueRecursive($uxon, $path, $propertyName, $rootValue, $prototypeClass);
    }
}