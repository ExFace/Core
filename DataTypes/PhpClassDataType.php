<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\InvalidArgumentException;

/**
 * Data type for PHP class names.
 * 
 * @author Andrej Kabachnik
 *
 */
class PhpClassDataType extends StringDataType
{
    const NAMESPACE_SEPARATOR = "\\";
    
    /**
     * 
     * @param object|string $class
     * @return string|NULL
     */
    public static function findNamespace($class) : ?string
    {
        $className = self::findClassNameWithNamespace($class);
        return StringDataType::substringBefore($className, self::NAMESPACE_SEPARATOR, null, false, true);
    }
    
    /**
     *
     * @param object|string $class
     * @return string|NULL
     */
    public static function findClassNameWithNamespace($class) : ?string
    {
        switch (true) {
            case is_object($class):
                $className = self::NAMESPACE_SEPARATOR . get_class($class);
                break;
            case is_string($class):
                $className = $class;
                break;
            default:
                throw new InvalidArgumentException('Invalid input for PhpClassDataType::findClassNameWithNamespace(): expecting class instance or string!');
        }
        return $className;
    }
    
    /**
     *
     * @param object|string $class
     * @return string|NULL
     */
    public static function findClassNameWithoutNamespace($class) : string
    {
        $className = self::findClassNameWithNamespace($class);
        return StringDataType::substringAfter($className, self::NAMESPACE_SEPARATOR, $className, false, true);
    }
}