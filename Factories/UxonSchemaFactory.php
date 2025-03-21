<?php
namespace exface\Core\Factories;

use exface\Core\DataTypes\UxonSchemaDataType;
use exface\Core\Uxon\UxonSchema;
use exface\Core\Interfaces\UxonSchemaInterface;
use exface\Core\Uxon\WidgetSchema;
use exface\Core\Uxon\ActionSchema;
use exface\Core\Uxon\DatatypeSchema;
use exface\Core\Uxon\BehaviorSchema;
use exface\Core\Uxon\ConnectionSchema;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Uxon\QueryBuilderSchema;

/**
 * Produces UXON schema classes for examining and validating UXON descriptions.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class UxonSchemaFactory extends AbstractStaticFactory
{
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $schemaName
     * @return UxonSchemaInterface
     */
    public static function create(WorkbenchInterface $workbench, string $schemaName) : UxonSchemaInterface
    {
        switch (mb_strtolower($schemaName)) {
            case UxonSchemaDataType::WIDGET:
                $schema = new WidgetSchema($workbench);
                break;
            case UxonSchemaDataType::ACTION:
                $schema = new ActionSchema($workbench);
                break;
            case UxonSchemaDataType::DATATYPE:
                $schema = new DatatypeSchema($workbench);
                break;
            case UxonSchemaDataType::BEHAVIOR:
                $schema = new BehaviorSchema($workbench);
                break;
            case UxonSchemaDataType::CONNECTION:
                $schema = new ConnectionSchema($workbench);
                break;
            case UxonSchemaDataType::QUERYBUILDER:
                $schema = new QueryBuilderSchema($workbench);
                break;
            case UxonSchemaDataType::QUERYBUILDER_ATTRIBUTE:
                $schema = new QueryBuilderSchema($workbench, null, QueryBuilderSchema::LEVEL_ATTRIBUTE);
                break;
            case UxonSchemaDataType::QUERYBUILDER_OBJECT:
                $schema = new QueryBuilderSchema($workbench, null, QueryBuilderSchema::LEVEL_OBJECT);
                break;
            default:
                if (substr($schemaName, 0, 1) === '\\' && class_exists($schemaName)) {
                    $schema = new $schemaName($workbench);
                } else {
                    $schema = new UxonSchema($workbench);
                }
                break;
        }
        return $schema;
    }
}