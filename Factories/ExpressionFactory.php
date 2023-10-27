<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\WorkbenchInterface;

abstract class ExpressionFactory
{
    private static $cache = [];

    /**
     * Parses a string expression into an ExFace expression object.
     * It is highly recommended to pass the meta object, the expression is related to as well. Otherwise
     * attribute_aliases cannot be parsed properly.
     * TODO Make the object a mandatory parameter. This requires a lot of changes to formulas, however. Probably will do that when rewriting the formula parser.
     *
     * @param WorkbenchInterface $exface            
     * @param string|mixed|NULL $expression            
     * @param MetaObjectInterface $object            
     * @return ExpressionInterface
     */
    public static function createFromString(WorkbenchInterface $exface, $scalar, MetaObjectInterface $object = null, bool $treatUnknownAsString = false) : ExpressionInterface
    {
        // IDEA cache expressions within the workbench instead of a static cache?
        
        // Be carefull when caching as the Expression parser might see a difference in
        // similar values of different types: e.g. `0`, `false` and `'0'` will yield
        // the same cache key, but are different expressions! This is why the cache
        // key gets the type as prefix.
        $objKey = $object === null ? 'null' : $object->getId()  . '-' . $treatUnknownAsString;
        $type = gettype($scalar);
        if ($type === 'boolean') {
            // Convert the boolean value `true` to `1` and the
            // boolean value `false` to '0' because else PHP automatically
            // converts `true` to `1` when it is treated as a string but
            // converts `false` to `` instead of `0`
            $string = $scalar ? '1' : '0';
        } else {
            $string = $scalar;
        }
        $exprKey = $type . ':' . $string;
        if (! isset(self::$cache[$exprKey]) || ! isset(self::$cache[$exprKey][$objKey])) {
            $expr = new Expression($exface, $scalar, $object, true, $treatUnknownAsString);
            self::$cache[$exprKey][$objKey] = $expr;
        } else {
            $expr = self::$cache[$exprKey][$objKey];
        }
        
        return $expr;
    }
    
    /**
     * Creates a scalar expression (number or string) without looking for formulas, etc.
     * 
     * 
     * 
     * @param WorkbenchInterface $workbench
     * @param string|NULL $string
     * @param MetaObjectInterface $object
     * @return ExpressionInterface
     */
    public static function createAsScalar(WorkbenchInterface $workbench, $string, MetaObjectInterface $object = null) : ExpressionInterface
    {
        return new Expression($workbench, $string, $object, false);
    }   

    /**
     *
     * @param MetaAttributeInterface $attribute            
     * @return ExpressionInterface
     */
    public static function createFromAttribute(MetaAttributeInterface $attribute) : ExpressionInterface
    {
        $exface = $attribute->getObject()->getWorkbench();
        return static::createFromString($exface, $attribute->getAliasWithRelationPath(), $attribute->getRelationPath()->getStartObject());
    }
    
    /**
     * Resolves an expression string relative to the given object and returns the resulting expression.
     * 
     * @param MetaObjectInterface $object
     * @param string $expression
     * @return ExpressionInterface
     */
    public static function createForObject(MetaObjectInterface $object, string $expression) : ExpressionInterface
    {
        return static::createFromString($object->getWorkbench(), $expression, $object);
    }
}
?>