<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Exceptions\FormulaError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\StringDataType;

/**
 * Returns the values of an enumeration used for a given attribute in the metamodel (optionally filtered).
 * 
 * ### Filters
 * 
 * You can filter the results by providing a filter string as third parameters:
 * - The general syntax is `'[label]<Comparator><Value>'`
 * 
 * You can use the following filters:
 * - `'=40'` (IS) and `'!=40'` (NOT IS): Checks if both values are of the same datatype.
 * - `'==40'` (EQUALS) and `'!==40'` (NOT EQUALS): Checks if both values are identical (datatype and value).
 * - `'[20,40'` (IN) and `'![20,40'` (NOT IN): Checks if the input value can be found in the given list of values.
 * - `'<40'` (LESS) and `'>40'` (GREATER): Checks if the input value is less or greater than the given value.
 * - `'<=40'` (LESS OR EQUAL) and `'>=40'` (GREATER OR EQUAL): Same as above, but matching equals as well.
 * 
 * You can filter for the label instead, if you prepend `label` before your comparator, like so:
 * - `=EnumValues('exface.Core.MONITOR_ERROR', 'STATUS','label==Ticket')` only returns entries where the label equals 'Ticket', i.e. '40'.
 * - `=EnumValues('exface.Core.MONITOR_ERROR', 'STATUS','label[New,Ticket')` returns entries, where the label is in the list 'New,Ticket', i.e. '10,40'.
 * 
 * ### Examples:
 * 
 * - `=EnumValues('exface.Core.MONITOR_ERROR', 'STATUS')` will give you status values of the error object
 * in the monitor, that is `10,15,20,40,90,99`
 * - `=EnumValues('exface.Core.MONITOR_ERROR', 'STATUS', '<90')` will give you status values less than 90,
 * that would be `10,15,20,40`
 * - `=EnumValues('exface.Core.MONITOR_ERROR', 'STATUS', 'label==Ticket')` will give you status values with the
 * specified label. In our example for 'Ticket' the output is '40'.
 * 
 * This is particularly useful for predefined filter values. For example, if you need a filter to show 
 * all monitored errors, that have not been dealt with yet, you can create a filter like this:
 * 
 * ```
 * 
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
        
        $values = $dataType->toArray();
        $delim = $attr->getValueListDelimiter();

        if($condition === null) {
            return implode($delim, array_keys($values));
        }

        if($filterForLabel = StringDataType::startsWith($condition, 'label')) {
            $condition = str_replace('label', '', $condition);
        }
        
        switch (true) {
            case StringDataType::startsWith($condition, ComparatorDataType::EQUALS):
                $compareVal = trim(substr($condition, 2));
                $comparer = function($val) use ($compareVal) {
                    return strtolower($val) === strtolower($compareVal);
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::EQUALS_NOT):
                $compareVal = trim(substr($condition, 3));
                $comparer = function($val) use ($compareVal) {
                    return strtolower($val) !== strtolower($compareVal);
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::IN):
                $compareVal = trim(substr($condition, 1));
                $compareVals = explode(',', $compareVal);
                $comparer = function ($val) use ($compareVals) {
                    return in_array($val, $compareVals);
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::NOT_IN):
                $compareVal = trim(substr($condition, 2));
                $compareVals = explode(',', $compareVal);
                $comparer = function ($val) use ($compareVals) {
                    return !in_array($val, $compareVals);
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::LESS_THAN):
                $compareVal = trim(substr($condition, 1));
                $comparer = function($val) use ($compareVal) {
                    return $val < $compareVal;
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::GREATER_THAN):
                $compareVal = trim(substr($condition, 1));
                $comparer = function($val) use ($compareVal) {
                    return $val > $compareVal;
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::LESS_THAN_OR_EQUALS):
                $compareVal = trim(substr($condition, 2));
                $comparer = function($val) use ($compareVal) {
                    return $val <= $compareVal;
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::GREATER_THAN_OR_EQUALS):
                $compareVal = trim(substr($condition, 2));
                $comparer = function($val) use ($compareVal) {
                    return $val >= $compareVal;
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::IS):
                $compareVal = trim(substr($condition, 1));
                $comparer = function($val) use ($compareVal) {
                    return strtolower($val) == strtolower($compareVal);
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::IS_NOT):
                $compareVal = trim(substr($condition, 2));
                $comparer = function($val) use ($compareVal) {
                    return strtolower($val) != strtolower($compareVal);
                };
                break;
            default:
                return implode($delim, array_keys($values));
        }
        
        $values = array_filter($values, function ($val, $key) use ($comparer, $filterForLabel) {
            return $comparer($filterForLabel ? $val : $key);
        }, ARRAY_FILTER_USE_BOTH);

        return implode($delim, array_keys($values));
    }
}