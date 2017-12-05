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
    protected function init()
    {
        // Integers do not use digit groups by default
        $this->setGroupDigits(false);
    }
    
    public function getPrecisionMax()
    {
        return 0;
    }
    
    public function getPrecisionMin()
    {
        return 0;
    }
    
    public function parse($string) 
    {
        return round(parent::parse($string), 0);
    }
}
?>