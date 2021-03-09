<?php
namespace exface\Core\Uxon;

use exface\Core\CommonLogic\DataSheets\DataSheetMapper;
use exface\Core\CommonLogic\UxonObject;

/**
 * UXON-schema class for data data sheet mappers.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSheetMapperSchema extends UxonSchema
{
    public function getPresets(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : array
    {
        $presets = [];
        $mappings = [];
        $objectTo = $this->getParentSchema()->getMetaObject($uxon, $path);
        
        // UID attribute
        if ($objectTo->hasUidAttribute()) {
            $presets[] = [
                'UID' => '',
                'NAME' => 'UID-attribute of to-object',
                'PROTOTYPE__LABEL' => 'To-object',
                'DESCRIPTION' => '',
                'PROTOTYPE' => DataSheetMapper::class,
                'UXON' => (new UxonObject([
                    'from_object_alias' => '',
                    'column_to_column_mappings' => [
                        'from' => '',
                        'to' => $objectTo->getUidAttributeAlias()
                    ]
                ]))->toJson()
            ];
        }
        
        // Editable attributes
        foreach ($objectTo->getAttributes()->getEditable() as $attr) {
            $mappings[] = [
                'from' => '',
                'to' => $attr->getAlias()
            ];
        }
        $presets[] = [
            'UID' => '',
            'NAME' => 'Editable attributes of to-object',
            'PROTOTYPE__LABEL' => 'To-object',
            'DESCRIPTION' => 'Includes column-to-column mappings for all editable attributes',
            'PROTOTYPE' => DataSheetMapper::class,
            'UXON' => (new UxonObject([
                'from_object_alias' => '',
                'column_to_column_mappings' => $mappings
            ]))->toJson(),
            'WRAP_PATH',
            'WRAP_FLAG',
            'THUMBNAIL'
        ];
        
        // All attributes
        foreach ($objectTo->getAttributes() as $attr) {
            $mappings[] = [
                'from' => '',
                'to' => $attr->getAlias()
            ];
        }
        $presets[] = [
            'UID' => '',
            'NAME' => 'All attributes of to-object', 
            'PROTOTYPE__LABEL' => 'To-object', 
            'DESCRIPTION' => 'Includes column-to-column mappings for all attributes of the object being mapped to. Use this preset if you have a lot of attributes to map and simply remove those you do not need', 
            'PROTOTYPE' => DataSheetMapper::class, 
            'UXON' => (new UxonObject([
                'from_object_alias' => '',
                'column_to_column_mappings' => $mappings
            ]))->toJson(), 
            'WRAP_PATH', 
            'WRAP_FLAG', 
            'THUMBNAIL'
        ];
        
        return $presets;
    }
    
    public static function getSchemaName() : string
    {
        return 'Data Sheet Mapper';
    }
    
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . DataSheetMapper::class;
    }
}