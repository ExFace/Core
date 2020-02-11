<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSheetDataType extends AbstractDataType
{
    /**
     *
     * @param mixed $val
     * @throws DataTypeCastingError
     * @return array
     */
    public static function cast($val)
    {
        if ($val instanceof DataSheetInterface) {
            return $val;
        }
        
        throw new DataTypeCastingError('Cannot cast ' . gettype($val) . ' to data sheet!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::parse()
     */
    public function parse($value)
    {
        if ($value instanceof DataSheetInterface) {
            return $value;
        }
        if ($value instanceof UxonObject) {
            try {
                return DataSheetFactory::createFromUxon($this->getWorkbench(), $value);
            } catch (\Throwable $e) {
                throw new DataTypeCastingError('Cannot cast UXON ' . $value->toJson() . ' to data sheet!', null, $e);
            }
        }
        
        throw new DataTypeValidationError($this, 'Cannot parse ' . gettype($value) . ' as data sheet!');
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::isValueEmpty()
     */
    public static function isValueEmpty($val) : bool
    {
        return empty($val) === true;
    }
}