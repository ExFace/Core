<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use Monolog\Logger;

/**
 * Enumeration for widget visibilities: normal, promoted, hidden and optional.
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
        return array_combine($this->getKeysStatic(), $this->getKeysStatic());
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
    public static function compareLogLevels($level1, $level2)
    {
        return Logger::toMonologLevel($level1) - Logger::toMonologLevel($level2);
    }
}