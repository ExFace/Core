<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Time zones available in PHP
 * 
 * @author Andrej Kabachnik
 *
 */
class TimeZoneDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    /**
     * Returns all possible values as an array
     *
     * @return string[] Constant name in key, constant value in value
     * @see EnumStaticDataTypeTrait::getValuesStatic()
     */
    public static function getValuesStatic()
    {
        $class = get_called_class();
        if (! array_key_exists($class, TimeZoneDataType::$cache)) {
            $phpTimeZones = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);
            TimeZoneDataType::$cache[$class] = array_combine($phpTimeZones, $phpTimeZones);
        }
        
        return TimeZoneDataType::$cache[$class];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        return $this::getValuesStatic();
    }
    
    /**
     * 
     * @param string $value
     * @return boolean
     */
    public static function isValidStaticValue($value)
    {
        if ($value === null || $value === '') {
            return false;
        }
        return \IntlTimeZone::getCanonicalID($value) !== false;
    }
}