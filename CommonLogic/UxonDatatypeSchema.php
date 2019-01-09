<?php
namespace exface\Core\CommonLogic;

use exface\Core\Factories\SelectorFactory;
use exface\Core\Factories\DataTypeFactory;

/**
 * UXON-schema class for data types.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonDatatypeSchema extends UxonSchema
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\UxonSchema::getEntityClass()
     */
    public function getEntityClass(UxonObject $uxon, array $path, string $rootEntityClass = '\exface\Core\CommonLogic\DataTypes\AbstractDataType') : string
    {
        $name = $rootEntityClass;
        
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
     * Returns the entity class for a given data type selector (e.g. alias).
     * 
     * @param string $selectorString
     * @return string
     */
    protected function getEntityClassFromSelector(string $selectorString) : string
    {
        try {
            $selector = SelectorFactory::createDataTypeSelector($this->getWorkbench(), $selectorString);
            $instance = DataTypeFactory::create($selector);
        } catch (\Throwable $e) {
            return '\exface\Core\CommonLogic\AbstractAction';
        }
        return get_class($instance);
    }
}