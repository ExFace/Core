<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Exceptions\FormulaError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\StringDataType;

/**
 * Looks up the values of an enumeration used for a given attribute in the metamodel, based on a specified filter.
 * 
 * ### Parameters
 *
 * `=EnumLookup('namespace.ObjectAlias','AttributeAlias','Comparator',CompareValue,'Instructions')`
 * - `'namespace.ObjectAlias'` (required): The object alias the enum belongs to.
 * - `'AttributeAlias'` (required): The attribute the enum belongs to.
 * - `'Comparator'` (required): The comparator used to filter the result (see below). Must be a string.
 * - `CompareValue` (required): The value used to filter the result. If you pass a string (enclosed in `''` quotation), the formula will be evaluated
 * statically (i.e. the compare value will be the same every time it is evaluated). If you pass an object (without quotation), the formula will be
 * evaluated dynamically (i.e. the compare value will be treated as an attribute alias, from which a new compare value is read for each row).
 * - `'Instructions'` (optional): You can specify, whether you would like to match the compare value against the values or labels of the enum.
 * By default, `'value'` is passed, but you can choose to pass `'label'` to compare against labels instead.
 * 
 * ### Comparators
 * 
 * You can use the following comparators:
 * - `'='` (IS) and `'!='` (NOT IS): Checks if both values are of the same datatype.
 * - `'=='` (EQUALS) and `'!=='` (NOT EQUALS): Checks if both values are identical (datatype and value).
 * - `'['` (IN) and `'!['` (NOT IN): Checks if the input value can be found in the given list of values.
 * - `'<'` (LESS) and `'>'` (GREATER): Checks if the input value is less or greater than the given value.
 * - `'<='` (LESS OR EQUAL) and `'>='` (GREATER OR EQUAL): Same as above, but matching equals as well.
 * 
 * ### Examples:
 * 
 * - `=EnumLookup('exface.Core.MONITOR_ERROR', 'STATUS', '!==', '')` will always give you status values of the error object
 * in the monitor, that is `10,15,20,40,90,99`
 * - `=EnumLookup('exface.Core.MONITOR_ERROR', 'STATUS', '<', '90')` will always give you status values less than 90,
 * that would be `10,15,20,40`
 * - `=EnumLookup('exface.Core.MONITOR_ERROR', 'STATUS', '==', 'Ticket', 'label')` will give you status values with the
 * specified label. In our example for 'Ticket' the output is '40'.
 * - `=EnumLookup('exface.Core.MONITOR_ERROR', 'STATUS', '==', StatusText, 'label')` will return different values, depending on 
 * the cell contents for `StatusText` in each row.
 * 
 * This is particularly useful for predefined filter values. For example, if you need a filter to show 
 * all monitored errors, that have not been dealt with yet, you can create a filter like this:
 * 
 * ```
 * 
 *  {
 *      "widget_type": "DataTable",
 *      "object_alias": "axenox.ETL.file_upload",
 *      "columns": [
 *          {
 *              "attribute_alias": "LABEL"
 *          },
 *          {
 *              "attribute_alias": "flow_run__status"
 *          },
 *          {
 *              "calculation": "=EnumLookup('axenox.ETL.flow_run','status','!==',flow_run__status,'value')"
 *          }
 *      ]
 * }
 * 
 * ```
 * 
 * @author Georg Bieger
 *        
 */
class EnumLookup extends Formula
{

    /**
     *
     * @param string|null $objectAlias
     * @param string|null $attributeAlias
     * @param string|null $condition
     * @param string|null $compareValue
     * @param string      $instructions
     * @return string
     */
    public function run(
        string $objectAlias = null, 
        string $attributeAlias = null, 
        string $condition = null, 
        string $compareValue = null,
        string $instructions = 'value'
    )
    {
        if (!$objectAlias || !$attributeAlias) {
            return null;
        }
        
        $attr = MetaObjectFactory::createFromString($this->getWorkbench(), $objectAlias)->getAttribute($attributeAlias);
        $dataType = $attr->getDataType();
        if (! ($dataType instanceof EnumDataTypeInterface)) {
            throw new FormulaError('Cannot use formula EnumLookup() on an attribute with non-enum data type');
        }
        
        $values = $dataType->toArray();
        $delim = $attr->getValueListDelimiter();

        if($condition === null) {
            return implode($delim, array_keys($values));
        }

        $filterForLabel = (str_contains($instructions, 'label'));
        $compareValue = strtolower($compareValue);
        
        switch (true) {
            case StringDataType::startsWith($condition, ComparatorDataType::EQUALS):
                $comparer = function($val) use ($compareValue) {
                    return strtolower($val) === $compareValue;
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::EQUALS_NOT):
                $comparer = function($val) use ($compareValue) {
                    return strtolower($val) !== $compareValue;
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::IS):
                $comparer = function($val) use ($compareValue) {
                    return strtolower($val) == $compareValue;
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::IS_NOT):
                $comparer = function($val) use ($compareValue) {
                    return strtolower($val) != $compareValue;
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::IN):
                $compareVals = explode(',', $compareValue);
                $comparer = function ($val) use ($compareVals) {
                    return in_array($val, $compareVals);
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::NOT_IN):
                $compareVals = explode(',', $compareValue);
                $comparer = function ($val) use ($compareVals) {
                    return !in_array($val, $compareVals);
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::LESS_THAN):
                $comparer = function($val) use ($compareValue) {
                    return $val < $compareValue;
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::GREATER_THAN):
                $comparer = function($val) use ($compareValue) {
                    return $val > $compareValue;
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::LESS_THAN_OR_EQUALS):
                $comparer = function($val) use ($compareValue) {
                    return $val <= $compareValue;
                };
                break;
            case StringDataType::startsWith($condition, ComparatorDataType::GREATER_THAN_OR_EQUALS):
                $comparer = function($val) use ($compareValue) {
                    return $val >= $compareValue;
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