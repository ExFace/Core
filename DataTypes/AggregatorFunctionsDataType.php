<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Exceptions\LogicException;

/**
 * Enumeration of aggregator function like SUM, AVG, etc.
 * 
 * @method AggregatorFunctionsDataType SUM(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType AVG(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType MIN(\exface\Core\CommonLogic\workbench $workbench)
 * @method AggregatorFunctionsDataType MAX(\exface\Core\CommonLogic\workbench $workbench)
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