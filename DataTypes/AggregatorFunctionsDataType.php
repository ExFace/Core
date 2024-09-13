<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Exceptions\LogicException;

/**
 * Enumeration of aggregator function like SUM, AVG, etc.
 * 
 * Aggregators are used as an extension for attribute aliases and relations paths to 
 * aggregate (total up) values values. For example, the following attribute aliases
 * can be used in a table for an `ORDER` object:
 * 
 * - `POSITION__ID:COUNT` - display the number of order positions
 * - `POSITION__QTY:SUM` - sum up all quantities
 * - `POSITION__MODIFIED_ON:MAX` - last modification daten
 * - `POSITION__STATUS:MAX_OF(MODIFIED_ON)` - the status of the last modified position
 * 
 * ## Available aggregators:
 * 
 * - `ATTRIUTE:SUM`
 * - `ATTRIUTE:AVG`
 * - `ATTRIUTE:MIN`
 * - `ATTRIUTE:MAX`
 * - `ATTRIUTE:MIN_OF(OTHER_ATTRIBUTE)` - value of `ATTRIBUTE` from the row with the minimum of `OTHER_ATTRIBUTE`
 * - `ATTRIUTE:MAX_OF(OTHER_ATTRIBUTE)` - value of `ATTRIBUTE` from the row with the maximum of `OTHER_ATTRIBUTE`
 * - `ATTRIUTE:LIST`
 * - `ATTRIUTE:LIST(,)` - a list with an explicitly defined separator - `,` in this case
 * - `ATTRIUTE:LIST_DISTINCT`
 * - `ATTRIUTE:LIST_DISTINCT(,)` - a distinct list with an explicitly defined separator
 * - `ATTRIUTE:COUNT`
 * - `ATTRIUTE:COUNT_DISTINCT`
 * - `ATTRIUTE:COUNT_IF(OTHER_ATTRIBUTE > 0)` - currently only supports simple conditions with an attribute alias on the left and a scalar on the right. There MUST be spaces around the comparator!
 * 
 * @method AggregatorFunctionsDataType SUM(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType AVG(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType MIN(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType MAX(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType MIN_OF(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType MAX_OF(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType LIST_ALL(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType LIST_DISTINCT(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType COUNT(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType COUNT_DISTINCT(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType COUNT_IF(\exface\Core\CommonLogic\workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class AggregatorFunctionsDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const SUM = 'SUM';
    
    const AVG = 'AVG';
    
    const MIN = 'MIN';
    
    const MAX = 'MAX';
    
    const MIN_OF = 'MIN_OF';
    
    const MAX_OF = 'MAX_OF';
    
    const LIST_ALL = 'LIST';
    
    const LIST_DISTINCT = 'LIST_DISTINCT';
    
    const COUNT = 'COUNT';
    
    const COUNT_DISTINCT = 'COUNT_DISTINCT';
    
    const COUNT_IF = 'COUNT_IF';
    
    private $labels = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        if (empty($this->labels)) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            
            foreach (static::getValuesStatic() as $val) {
                $this->labels[$val] = $translator->translate('GLOBAL.AGGREGATOR_FUNCTIONS.' . $val);
            }
        }
        
        return $this->labels;
    }
    
    
    public function getSymbolOfValue($value = null) : ?string
    {
        $value = $value ?? $this->getValue();
        if ($value === null) {
            throw new LogicException('Cannot get text label for an enumeration value: neither an internal value exists, nor is one passed as parameter');
        }
        return $this::findSymbol($value);
    }
    
    public static function findSymbol($value) : ?string
    {
        switch (true) {
            case $value === self::SUM: return "Σ";
            case $value === self::AVG: return "Ø";
            case $value === self::COUNT:
            case $value === self::COUNT_IF:
            case $value === self::COUNT_DISTINCT: return "#";
        }
        return null;
    }
}