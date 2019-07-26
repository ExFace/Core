<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Factories\ExpressionFactory;

/**
 * Extended configuration for spreadsheet footers.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSpreadSheetFooter extends DataFooter
{
    const SPREAD_TO_FIRST_ONLY = 'first_row_only';
    const SPREAD_TO_LAST_ONLY = 'last_row_only';
    const SPREAD_PROPORTIONALLY = 'proportional';
    const SPREAD_EQUALLY = 'equal';
    
    private $fixedValue = null;
    
    private $fixedValueSpreadAlgorithm = null;
    
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        
        if ($this->hasFixedValue() === true) {
            $uxon->setProperty('fixed_value', $this->getFixedValue()->toString());
            $uxon->setProperty('fixed_value_spread', $this->getFixedValueSpread());
        }
        
        return $uxon;
    }
    
    /**
     *
     * @return ExpressionInterface
     */
    public function getFixedValue() : ExpressionInterface
    {
        if ($this->fixedValue !== null && ! $this->fixedValue instanceof ExpressionInterface) {
            $this->fixedValue = ExpressionFactory::createForObject($this->getMetaObject(), $this->fixedValue);
        }
        return $this->fixedValue;
    }
    
    /**
     * 
     * 
     * @uxon-property fixed_value
     * @uxon-type metamodel:expression
     * 
     * @param ExpressionInterface|string $value
     * @return DataSpreadSheetFooter
     */
    public function setFixedValue($value) : DataSpreadSheetFooter
    {
        $this->fixedValue = $value;
        return $this;
    }
    
    public function hasFixedValue() : bool
    {
        return $this->fixedValue !== null;
    }
    
    /**
     *
     * @return string
     */
    public function getFixedValueSpread() : string
    {
        return $this->fixedValueSpreadAlgorithm ?? self::SPREAD_TO_FIRST_ONLY;
    }
    
    /**
     * 
     * @uxon-property fixed_value_spread
     * @uxon-type [first_row_only,last_row_only,proportional,equal]
     * @uxon-default first_row_only
     * 
     * @param string $value
     * @return DataSpreadSheetFooter
     */
    public function setFixedValueSpread(string $value) : DataSpreadSheetFooter
    {
        $this->fixedValueSpreadAlgorithm = $value;
        return $this;
    }
}