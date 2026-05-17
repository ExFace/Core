<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\Debugger;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\DataTypes\JsonSchemaValidationError;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use JsonSchema\Validator;

class JsonSchemaDataType extends JsonDataType
{
    /**
     * Builds a JSON schema for DataSheet column definitions.
     *
     * @return array
     */
    public static function buildSchemaForDataSheetColumn() : array
    {
        return [
            'type' => 'object',
            'description' => 'DataSheet column definition',
            'additionalProperties' => false,
            'required' => ['expression'],
            'properties' => [
                'expression' => [
                    'type' => 'string',
                    'description' => 'Expression (attribute alias, formula or constant)'
                ]
            ]
        ];
    }

    /**
     * Builds a JSON schema for DataSheet sorter definitions.
     *
     * @return array
     */
    public static function buildSchemaForDataSheetSorter() : array
    {
        return [
            'type' => 'object',
            'required' => ['attribute_alias', 'direction'],
            'additionalProperties' => false,
            'properties' => [
                'attribute_alias' => [
                    'type' => 'string',
                    'description' => 'Attribute alias to sort by'
                ],
                'direction' => [
                    'type' => 'string',
                    'description' => 'Sort direction',
                    'enum' => [SortingDirectionsDataType::ASC, SortingDirectionsDataType::DESC]
                ]
            ]
        ];
    }

    /**
     * Builds a JSON schema for DataSheet aggregation definitions.
     *
     * @return array
     */
    public static function buildSchemaForDataSheetAggregators() : array
    {
        return [
            'type' => 'array',
            'description' => 'Array of aggregation definitions',
            'items' => [
                'type' => 'string',
                'description' => 'Aggregation expression string'
            ]
        ];
    }

    public static function buildSchemaForConditionGroup(int $depth = 1, bool $requireAllProperties = true) : array
    {
        $schema = [
            'type' => 'object',
            'description' => 'Condition group to filter the data',
            'required' => ['operator', 'conditions'],
            'additionalProperties' => false,
            'properties' => [
                'operator' => [
                    'type' => 'string',
                    'description' => 'Logical operator to combine conditions',
                    'enum' => [EXF_LOGICAL_AND, EXF_LOGICAL_OR, EXF_LOGICAL_XOR]
                ],
                'conditions' => [
                    'type' => 'array',
                    'description' => 'Array of conditions in this group',
                    'items' => self::buildSchemaForCondition()
                ]
            ]
        ];
        if ($depth > 0){
            $schema['properties']['nested_groups'] = [
                'type' => 'array',
                'description' => 'Nested condition groups for complex logic',
                'items' => self::buildSchemaForConditionGroup($depth-1)
            ];
        }

        if ($requireAllProperties) {
            $schema['required'] = array_keys($schema['properties']);
        }
        
        return $schema;
    }
    
    public static function buildSchemaForCondition() : array
    {
        return [
            'type' => 'object',
            'required' => ['expression', 'comparator', 'value'],
            'additionalProperties' => false,
            'properties' => [
                'expression' => [
                    'type' => 'string',
                    'description' => 'Attribute alias or expression (left side)'
                ],
                'comparator' => [
                    'type' => 'string',
                    'description' => 'Comparison operator: =, !=, ==, !==, <, <=, >, >=, [, ![, etc.',
                    'enum' => [
                        ComparatorDataType::IS,
                        ComparatorDataType::IS_NOT,
                        ComparatorDataType::EQUALS,
                        ComparatorDataType::EQUALS_NOT,
                        ComparatorDataType::LESS_THAN,
                        ComparatorDataType::LESS_THAN_OR_EQUALS,
                        ComparatorDataType::GREATER_THAN,
                        ComparatorDataType::GREATER_THAN_OR_EQUALS,
                        ComparatorDataType::IN,
                        ComparatorDataType::NOT_IN,
                        ComparatorDataType::LIST_INTERSECTS,
                        ComparatorDataType::LIST_NOT_INTERSECTS,
                        ComparatorDataType::LIST_SUBSET,
                        ComparatorDataType::LIST_NOT_SUBSET,
                        ComparatorDataType::LIST_EACH_IS,
                        ComparatorDataType::LIST_EACH_IS_NOT,
                        ComparatorDataType::LIST_EACH_EQUALS,
                        ComparatorDataType::LIST_EACH_EQUALS_NOT,
                        ComparatorDataType::LIST_EACH_LESS_THAN,
                        ComparatorDataType::LIST_EACH_LESS_THAN_OR_EQUALS,
                        ComparatorDataType::LIST_EACH_GREATER_THAN,
                        ComparatorDataType::LIST_EACH_GREATER_THAN_OR_EQUALS,
                        ComparatorDataType::LIST_ANY_IS,
                        ComparatorDataType::LIST_ANY_IS_NOT,
                        ComparatorDataType::LIST_ANY_EQUALS,
                        ComparatorDataType::LIST_ANY_EQUALS_NOT,
                        ComparatorDataType::LIST_ANY_LESS_THAN,
                        ComparatorDataType::LIST_ANY_LESS_THAN_OR_EQUALS,
                        ComparatorDataType::LIST_ANY_GREATER_THAN,
                        ComparatorDataType::LIST_ANY_GREATER_THAN_OR_EQUALS,
                        ComparatorDataType::BETWEEN
                    ]
                ],
                'value' => [
                    'type' => 'string',
                    'description' => 'Value to compare against (right side)'
                ]
            ]
        ];
    }
}