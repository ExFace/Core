<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumDynamicDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Numeric enumeration - list of allowed numeric values.
 * 
 * @author Andrej Kabachnik
 *
 */
class NumberEnumDataType extends NumberDataType implements EnumDataTypeInterface
{
    use EnumDynamicDataTypeTrait {
        format as formatEnum;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\NumberDataType::format()
     */
    public function format($value = null, string $format = null, $ifNull = '') : string
    {
        if ($value === null || $value === '' || $value === EXF_LOGICAL_NULL) {
            return $ifNull;
        }
        
        return $this->formatEnum($value);
    }
}