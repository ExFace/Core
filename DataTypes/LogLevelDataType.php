<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use Monolog\Logger;

/**
 * Enumeration for widget PSR-3 log levels: debug, info, error, critical, etc.
 * 
 * Can be used for string values as defined in the PSR-3 standard as well as for
 * numeric values as used in the popular PHP library monolog depending on the
 * `use_numeric_values` property.
 * 
 * @method LogLevelDataType DEBUG(\exface\Core\CommonLogic\Workbench $workbench)
 * @method LogLevelDataType INFO(\exface\Core\CommonLogic\Workbench $workbench)
 * @method LogLevelDataType NOTICE(\exface\Core\CommonLogic\Workbench $workbench)
 * @method LogLevelDataType WARNING(\exface\Core\CommonLogic\Workbench $workbench)
 * @method LogLevelDataType ERROR(\exface\Core\CommonLogic\Workbench $workbench)
 * @method LogLevelDataType CRITICAL(\exface\Core\CommonLogic\Workbench $workbench)
 * @method LogLevelDataType ALERT(\exface\Core\CommonLogic\Workbench $workbench)
 * @method LogLevelDataType EMERGENCY(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class LogLevelDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    private $numericLevels = false;
    
    /**
     * Returns all possible values as an array
     *
     * @return string[] Constant name in key, constant value in value
     * @see EnumStaticDataTypeTrait::getValuesStatic()
     */
    public static function getValuesStatic()
    {
        $class = get_called_class();
        if (!array_key_exists($class, static::$cache)) {
            $reflection            = new \ReflectionClass(LoggerInterface::class);
            static::$cache[$class] = $reflection->getConstants();
        }
        
        return static::$cache[$class];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        if ($this->isUsingNumericLevels()) {
            return $this->getValuesOfMonologLevels();
        } else {
            return array_combine($this->getKeysStatic(), array_map(function($word) {
                return ucfirst(strtolower($word));
            }, $this->getKeysStatic()));
        }
    }
    
    /**
    * Compares PSR 3 log levels. It uses monolog to do so.
    *
    * @param string $level1
    * @param string $level2
    *
    * @return int Result is < 0 if monolog values of level1 < level2. Result is > 0 if monolog values of level1 >
    * level2. Result is 0 if monolog values of level1 and level2 are equal.
    */
    public static function compareLogLevels(string $level1, string $level2) : int
    {
        return Logger::toMonologLevel($level1) - Logger::toMonologLevel($level2);
    }
    
    /**
     * 
     * @param string $psrLevel
     * @return int
     */
    public static function convertToMonologLevel(string $psrLevel) : int
    {
        return Logger::toMonologLevel($psrLevel);
    }
    
    /**
     * 
     * @param int $monologLevel
     * @return string
     */
    public static function convertToPsrLevel(int $monologLevel) : string
    {
        return Logger::getLevelName($monologLevel);
    }
    
    /**
     * Similar to `getValues()` but uses numeric monolog levels for keys
     * @return string[]
     */
    public static function getValuesOfMonologLevels() : array
    {
        return array_flip(logger::getLevels());
    }
    
    public function setUseNumericLevels(bool $trueOrFalse) : LogLevelDataType
    {
        $this->numericLevels = $trueOrFalse;
        return $this;
    }
    
    /**
     * Set to TRUE to use numeric Monolog levels instead of PSR 3 string levels
     * 
     * @uxon-property use_numeric_levels
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @return bool
     */
    public function isUsingNumericLevels() : bool
    {
        return $this->numericLevels;
    }
}