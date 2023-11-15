<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Exceptions\FormulaError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\StringDataType;

/**
 * Returns the values of an enumeration used for a given attribute in the metamodel (optionally filtered)
 * 
 * Examples:
 * 
 * - `=EnumValues('exface.Core.MONITOR_ERROR', 'STATUS')` will give you status values of the error object
 * in the monitor, that is `10,15,20,40,90,99`
 * - `=EnumValues('exface.Core.MONITOR_ERROR', 'STATUS', '<90')` will give you status values less than 90,
 * that would be `10,15,20,40`
 * 
 * This is particularly usedful for predefined filter values. For example, if you need a filter to show 
 * all monitored errors, that have not bee dealt with yet, you can create a filter like this:
 * 
 * ```
 *  {
 *      "attribute_alias": "STATUS",
 *      "input_widget": {
 *          "widget_type": "InputSelect",
 *          "multi_select": true,
 *          "value": "=EnumValues('exface.Core.MONITOR_ERROR', 'STATUS', '<90')"
 *      }
 *  }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class EnumValues extends Formula
{

    /**
     * 
     * @param string $objectAlias
     * @param string $attributeAlias
     * @param string $condition
     * @throws FormulaError
     * @return string
     */
    public function run(string $objectAlias = null, string $attributeAlias = null, string $condition = null)
    {
        if (! $objectAlias || ! $attributeAlias) {
            return null;
        }
        $attr = MetaObjectFactory::createFromString($this->getWorkbench(), $objectAlias)->getAttribute($attributeAlias);
        $dataType = $attr->getDataType();
        if (! ($dataType instanceof EnumDataTypeInterface)) {
            throw new FormulaError('Cannot use formula EnumValues() on an attribute with non-enum data type');
        }
        $values = $dataType->getValues();
        $delim = $attr->getValueListDelimiter();
        
        if ($condition !== null) {
            switch (true) {
                case StringDataType::startsWith($condition, ComparatorDataType::EQUALS_NOT):
                    $compareVal = trim(substr($condition, 3));
                    $values = array_filter($values, function($val) use ($compareVal) {
                        return strtolower($val) !== strtolower($compareVal);
                    });
                    break;
                case StringDataType::startsWith($condition, ComparatorDataType::EQUALS):
                    $compareVal = trim(substr($condition, 2));
                    $values = array_filter($values, function($val) use ($compareVal) {
                        return strtolower($val) === strtolower($compareVal);
                    });
                    break;
                case StringDataType::startsWith($condition, ComparatorDataType::LESS_THAN_OR_EQUALS):
                    $compareVal = trim(substr($condition, 2));
                    $values = array_filter($values, function($val) use ($compareVal) {
                        return $val <= $compareVal;
                    });
                    break;
                case StringDataType::startsWith($condition, ComparatorDataType::GREATER_THAN_OR_EQUALS):
                    $compareVal = trim(substr($condition, 2));
                    $values = array_filter($values, function($val) use ($compareVal) {
                        return $val >= $compareVal;
                    });
                    break;
                case StringDataType::startsWith($condition, ComparatorDataType::NOT_IN):
                    $compareVal = trim(substr($condition, 2));
                    $compareVals = explode(',', $compareVal);
                    $values = array_diff($values, $compareVals);
                    break;
                case StringDataType::startsWith($condition, ComparatorDataType::IS_NOT):
                    $compareVal = trim(substr($condition, 2));
                    $values = array_filter($values, function($val) use ($compareVal) {
                        return strtolower($val) != strtolower($compareVal);
                    });
                    break;
                case StringDataType::startsWith($condition, ComparatorDataType::LESS_THAN):
                    $compareVal = trim(substr($condition, 1));
                    $values = array_filter($values, function($val) use ($compareVal) {
                        return $val < $compareVal;
                    });
                    break;
                case StringDataType::startsWith($condition, ComparatorDataType::GREATER_THAN):
                    $compareVal = trim(substr($condition, 1));
                    $values = array_filter($values, function($val) use ($compareVal) {
                        return $val > $compareVal;
                    });
                    break;
                case StringDataType::startsWith($condition, ComparatorDataType::IS):
                    $compareVal = trim(substr($condition, 1));
                    $values = array_filter($values, function($val) use ($compareVal) {
                        return strtolower($val) == strtolower($compareVal);
                    });
                    break;
            }
        }
        
        return implode($delim, $values);
    }
}