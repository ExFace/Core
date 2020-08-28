<?php
namespace exface\Core\Factories;

use exface\Core\Uxon\UxonSchema;
use exface\Core\Interfaces\UxonSchemaInterface;
use exface\Core\Uxon\WidgetSchema;
use exface\Core\Uxon\ActionSchema;
use exface\Core\Uxon\DatatypeSchema;
use exface\Core\Uxon\BehaviorSchema;
use exface\Core\Uxon\ConnectionSchema;
use exface\Core\Interfaces\WorkbenchInterface;

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
            case UxonSchema::SCHEMA_WIDGET:
                $schema = new WidgetSchema($workbench);
                break;
            case UxonSchema::SCHEMA_ACTION:
                $schema = new ActionSchema($workbench);
                break;
            case UxonSchema::SCHEMA_DATATYPE:
                $schema = new DatatypeSchema($workbench);
                break;
            case UxonSchema::SCHEMA_BEHAVIOR:
                $schema = new BehaviorSchema($workbench);
                break;
            case UxonSchema::SCHEMA_CONNECTION:
                $schema = new ConnectionSchema($workbench);
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