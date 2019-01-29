<?php
namespace exface\Core\DataTypes;

/**
 * All whole numbers are considered integers.
 * 
 * Minimum and maximum values can be configured. Precision options can be set, but
 * will have no effect.
 * 
 * @author Andrej Kabachnik
 *
 */
class IntegerDataType extends NumberDataType
{
    /**
     * 
     */
    protected function init()
    {
        // Integers do not use digit groups by default
        $this->setGroupDigits(false);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\NumberDataType::getPrecisionMax()
     */
    public function getPrecisionMax()
    {
        return 0;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\NumberDataType::getPrecisionMin()
     */
    public function getPrecisionMin()
    {
        return 0;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\NumberDataType::parse()
     */
    public function parse($string) 
    {
        $num = parent::parse($string);
        if ($num) {
            // Only round non-empty values - otherwise empty will become 0!
            return round($num, 0);
        }
        return $num;
    }
}
?>