<?php
namespace exface\Core\Uxon;

use exface\Core\Factories\SelectorFactory;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\DataTypes\UxonSchemaNameDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * UXON-schema class for data types.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class DatatypeSchema extends UxonSchema
{
    
    public static function getSchemaName() : string
    {
        return UxonSchemaNameDataType::DATATYPE;
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
     * Returns the prototype class for a given data type selector (e.g. alias).
     * 
     * @param string $selectorString
     * @return string
     */
    protected function getPrototypeClassFromSelector(string $selectorString) : string
    {
        try {
            $selector = SelectorFactory::createDataTypeSelector($this->getWorkbench(), $selectorString);
            $instance = DataTypeFactory::create($selector);
        } catch (\Throwable $e) {
            $ex = new RuntimeException('Error loading data type autosuggest - falling back to "AbstractDataType"!', null, $e);
            $this->getWorkbench()->getLogger()->logException($ex, LoggerInterface::DEBUG);
            return $this->getDefaultPrototypeClass();
        }
        return get_class($instance);
    }
    
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractDataType::class;
    }
}