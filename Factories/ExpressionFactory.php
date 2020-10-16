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
     * @param string $expression            
     * @param MetaObjectInterface $object            
     * @return ExpressionInterface
     */
    public static function createFromString(WorkbenchInterface $exface, $string, MetaObjectInterface $object = null, bool $treatUnknownAsString = false) : ExpressionInterface
    {
        // IDEA cache expressions within the workbench instead of a static cache?
        
        $objId = $object === null ? 'null' : $object->getId()  . '-' . $treatUnknownAsString;
        
        if (! isset(self::$cache[$string]) || ! isset(self::$cache[$string][$objId])) {
            $expr = new Expression($exface, $string, $object, true, $treatUnknownAsString);
            self::$cache[$string][$objId] = $expr;
        } else {
            $expr = self::$cache[$string][$objId];
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