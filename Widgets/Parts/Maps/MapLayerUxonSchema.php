<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Uxon\UxonSchema;
use exface\Core\Widgets\Map;

/**
 * UXON-schema class for map layer widget parts.
 * 
 * This schema loads the correct widget part depending on the `type` property of
 * series UXON.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class MapLayerUxonSchema extends UxonSchema
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPrototypeClass()
     */
    public function getPrototypeClass(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : string
    {
        $name = $rootPrototypeClass ?? $this->getDefaultPrototypeClass();
        
        foreach ($uxon as $key => $value) {
            if (strcasecmp($key, 'type') === 0) {
                $class = Map::getLayerClassFromType($value);
                if ($this->validatePrototypeClass($class) === true) {
                    $name = $class;
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPropertyValueRecursive()
     */
    public function getPropertyValueRecursive(UxonObject $uxon, array $path, string $propertyName, string $rootValue = '')
    {
        if ($propertyName === 'object_alias' && $dataUxon = $uxon->getProperty('data')) {
            if ($dataUxon->hasProperty('object_alias')) {
                return $dataUxon->getProperty('object_alias');
            }
        }
        return parent::getPropertyValueRecursive($uxon, $path, $propertyName, $rootValue);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getDefaultPrototypeClass()
     */
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractMapLayer::class;
    }
}