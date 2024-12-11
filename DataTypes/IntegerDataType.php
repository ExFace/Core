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
    public static function cast($string)
    {
        $num = parent::cast($string);
        if ($num && is_numeric($num)) {
            // Only round non-empty values - otherwise empty will become 0!
            return round($num, 0);
        }
        return $num;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\NumberDataType::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->unsetProperty('precision_min');
        $uxon->unsetProperty('precision_max');
        if ($uxon->hasProperty('group_digits') && $uxon->getProperty('group_digits') !== true) {
            $uxon->unsetProperty('group_digits');
        }
        return $uxon;
    }
}