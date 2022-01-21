<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Exceptions\FormulaError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\StringDataType;

/**
 * Returns the values of an enumeration data type as a list (optionally filtered)
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