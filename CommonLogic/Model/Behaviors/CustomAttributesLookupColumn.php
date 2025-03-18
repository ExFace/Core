<?php

namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\ExpressionInterface;

/**
 * Additional column to be loaded with the lookup data for custom attribtues
 * 
 * For example, if you have a set of custom attributes, that should have a ColorIndicator 
 * as default display widget and that ColorIndicator uses a color from a specific data
 * column, you will need that column every time you want to display any of the custom
 * attributes. However, the color data column will likely depend on the data of the 
 * custom attribute, so it needs to be loaded with the data and transposed.
 * 
 * For example, lets assume we have a generic KPI system for clients. There is an object
 * `CLIENT_KPI`, that contains `VALUE` and `COLOR` for every KPI of a specific client. There
 * is also a `KPI_DEFINITION` object with a `CustomAttributesDefinitionBehavior`, that holds
 * information about every possible KPI. Now we need every KPI to be displayed as a 
 * ColorIndicator with the correct color:
 * 
 * ```
 * {
 *   "attributes_definition": {
 *     "object_alias": "my.APP.KPI_DEFINITION",
 *   },
 *   "attributes_defaults": {
 *     "default_display_widget": {
 *       "widget_type": "ColorIndicator",
 *       "color": {
 *         "data_column_name": "_[#~custom_attribute_alias#]_COLOR"
 *       }
 *     }
 *   },
 *   "values_lookup": {
 *     "object_alias": "my.APP.CLIENT_KPIS",
 *     "relation_to_behavior_object": "CLIENT",
 *     "values_attribute_alias_column": "KPI_DEFINITION__ALIAS",
 *     "values_content_column": "VALUE",
 *     "additional_columns": [
 *       {
 *         "attribute_alias": "COLOR",
 *         "column_name": "_[#~custom_attribute_alias#]_COLOR"
 *       }
 *     ]
 *   }
 * }
 * 
 * ```
 * 
 * @see \exface\Core\Behaviors\CustomAttributeDefinitionBehavior
 * 
 * @author Andrej Kabachnik
 */
class CustomAttributesLookupColumn implements iCanBeConvertedToUxon
{
    use ImportUxonObjectTrait;

    private $lookup = null;

    private $expression = null;

    private $expressionString = null;

    private $columnName = null;

    /**
     * 
     * @param \exface\Core\Interfaces\Model\BehaviorInterface $behavior
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     */
    public function __construct(CustomAttributesLookup $lookup, UxonObject $uxon)
    {
        $this->lookup = $lookup;
        $this->importUxonObject($uxon);
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            'column_name' => $this->columnName,
            'attribute_alias' => $this->expressionString
        ]);
        return $uxon;
    }

    /**
     * 
     * @return ExpressionInterface
     */
    public function getLookupExpression() : ExpressionInterface
    {
        if ($this->expression === null) {
            $this->expression = ExpressionFactory::createForObject($this->lookup->getObject(), $this->expressionString);
        }
        return $this->expression;
    }

    /**
     * Additional attribute to read
     * 
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $aliasWithRelationPath
     * @return CustomAttributesLookupColumn
     */
    private function setAttributeAlias(string $aliasWithRelationPath) : CustomAttributesLookupColumn
    {
        $this->expressionString = $aliasWithRelationPath;
        return $this;
    }

    /**
     * Calculation formula instead of a regular attribute_alias
     * 
     * @uxon-property calculation
     * @uxon-type metamodel:formula
     * 
     * @param string $formula
     * @return CustomAttributesLookupColumn
     */
    private function setCalculation(string $formula) : CustomAttributesLookupColumn
    {
        $this->expressionString = $formula;
        return $this;
    }

    /**
     * 
     * @param string[] $placeholders
     * @return string
     */
    public function getColumnName(array $placeholders) : string
    {
        return StringDataType::replacePlaceholders($this->columnName, $placeholders);
    }

    /**
     * The name for the column in the resulting data of the main object - e.g. for use in custom display/editor widgets
     * 
     * Since the resulting data will contain a separate column for every custom attribute, the
     * data of the additional columns also need to be separated into different columns - one
     * additional column for every custom attribute. The names of these additional columns
     * are defined here using the `[#~custom_attribute_alias#]` placeholder to make sure they
     * "visually" belong to their custom attributes.
     * 
     * @uxon-property column_name
     * @uxon-type string
     * @uxon-template _[#~custom_attribute_alias#]_MYCOL
     * 
     * @param string $name
     * @return CustomAttributesLookupColumn
     */
    private function setColumnName(string $name) : CustomAttributesLookupColumn
    {
        $this->columnName = $name;
        return $this;
    }
}