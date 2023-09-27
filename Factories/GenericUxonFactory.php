<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;

class GenericUxonFactory extends AbstractStaticFactory
{
    const ARG_TYPE_CLASS = 'class';
    
    const ARG_TYPE_ARRAY = 'array';
    
    const ARG_TYPE_CONSTANT = 'constant';

    /**
     * Instantiates classes defined in a UXON description
     * 
     * Example:
     * 
     * ```
     *  {
     *      "__class": "\\League\\Flysystem\\Local\\LocalFilesystemAdapter",
     *      "__construct": [
     *          "/root/directory/",
     *          {
     *              "__class": \\League\\Flysystem\\UnixVisibility\\PortableVisibilityConverter",
     *              "fromArray": [
     *                  {
     *                      "file": {
     *                          "public": 640,
     *                          "private": 604
     *                      },
     *                      "dir": {
     *                          "public": 740,
     *                          "private": 7604
     *                      }
     *                  }
     *              ]
     *          },
     *          {
     *              "__constant": "LOCK_EX",
     *          },
     *          {
     *              "__constant": "\\League\\Flysystem\\Local\\LocalFilesystemAdapter::DISALLOW_LINKS",
     *          }
     *      ]
     *  }
     * 
     * ```
     *
     * @param UxonObject $uxon
     * @return object
     */
    public static function createFromUxon(UxonObject $uxon)
    {
        if (! $uxon->hasProperty('__class')) {
            throw new InvalidArgumentException('Cannot instantiate class from UXON description: requred property `__class` not defined!');
        }
        return static::instantiateClass($uxon);
    }
    
    /**
     * 
     * @param UxonObject $uxon
     * @return object|mixed|array
     */
    protected static function instantiate(UxonObject $uxon)
    {
        switch (static::getType($uxon)) {
            case static::ARG_TYPE_CLASS:
            case static::ARG_TYPE_CLASS:
                return static::instantiateClass($uxon);
            case static::ARG_TYPE_CONSTANT:
                return static::instantiateConstant($uxon);
            case static::ARG_TYPE_ARRAY:
                return static::instantiateArray($uxon);
        }
    }
    
    /**
     * 
     * @param UxonObject $uxon
     * @return object
     */
    protected static function instantiateClass(UxonObject $uxon) : object
    {
        $class = $uxon->getProperty('__class');
        $instance = null;
        
        if ($uxon->hasProperty('__construct')) {
            $args = static::getArguments($uxon->getProperty('__construct'));
            $reflector = new \ReflectionClass($class);
            $instance = $reflector->newInstanceArgs($args);
        } 
        
        foreach ($uxon->getPropertiesAll() as $method => $argsUxon) {
            if ($method === '__class' || $method === '__construct') {
                continue;
            }
            
            $args = static::getArguments($argsUxon);
            if ($instance === null) {
                $instance = call_user_func_array([$class, $method], $args);
            } else {
                call_user_func_array([$instance, $method], $args);
            }
        }
        
        return $instance;
    }
    
    protected static function getArguments($uxonOrScalar) : array
    {
        if ($uxonOrScalar instanceof UxonObject) {
            $argArray = array_values(static::instantiateArray($uxonOrScalar));
        } else {
            $argArray = [$uxonOrScalar];
        }
        return $argArray;
    }
    
    /**
     * 
     * @param UxonObject $uxon
     * @return mixed
     */
    protected static function instantiateConstant(UxonObject $uxon)
    {
        return constant($uxon->getProperty('__constant'));
    }
    
    /**
     * 
     * @param UxonObject $uxon
     * @return array
     */
    protected static function instantiateArray(UxonObject $uxon) : array
    {
        $args = [];
        foreach ($uxon->getPropertiesAll() as $name => $arg) {
            if ($arg instanceof UxonObject) {
                $args[$name] = static::instantiate($arg);
            } else {
                $args[$name] = $arg;
            }
        }
        return $args;
    }
    
    /**
     * 
     * @param UxonObject $uxon
     * @return string
     */
    protected static function getType(UxonObject $uxon) : string
    {
        switch (true) {
            case $uxon->hasProperty('__class'):
                return static::ARG_TYPE_CLASS;
            case $uxon->hasProperty('__constant'):
                return static::ARG_TYPE_CONSTANT;
            default:
                return static::ARG_TYPE_ARRAY;
        }
    }
}
?>