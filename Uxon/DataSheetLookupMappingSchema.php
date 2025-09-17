<?php
namespace exface\Core\Uxon;

use exface\Core\CommonLogic\DataSheets\Mappings\LookupMapping;
use exface\Core\CommonLogic\UxonObject;

/**
 * UXON-schema class lookup data mappings.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSheetLookupMappingSchema extends DataSheetMapperSchema
{       
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . LookupMapping::class;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPropertyValueRecursive()
     */
    public function getPropertyValueRecursive(UxonObject $uxon, array $path, string $propertyName, string $rootValue = '', string $prototypeClass = null)
    {
        if ($propertyName === 'object_alias' && $path[count($path)-1] === 'lookup' && $uxon->hasProperty('lookup_object_alias')) {
            return $uxon->getProperty('lookup_object_alias');
        }
        return parent::getPropertyValueRecursive($uxon, $path, $propertyName, $rootValue, $prototypeClass);
    }
}