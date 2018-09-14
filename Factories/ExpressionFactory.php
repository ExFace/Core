<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;

abstract class ExpressionFactory
{
    private static $cache = [];

    /**
     * Parses a string expression into an ExFace expression object.
     * It is highly recommended to pass the meta object, the expression is related to as well. Otherwise
     * attribute_aliases cannot be parsed properly.
     * TODO Make the object a mandatory parameter. This requires a lot of changes to formulas, however. Probably will do that when rewriting the formula parser.
     *
     * @param Workbench $exface            
     * @param string $expression            
     * @param MetaObjectInterface $object            
     * @return ExpressionInterface
     */
    public static function createFromString(Workbench $exface, $string, $meta_object = null) : ExpressionInterface
    {
        // IDEA cache expressions within the workbench instead of a static cache?
        
        $objId = $meta_object === null ? 'null' : $meta_object->getId();
        
        if (! isset(self::$cache[$string]) || ! isset(self::$cache[$string][$objId])) {
            $expr = new Expression($exface, $string, $meta_object);
            self::$cache[$string][$objId] = $expr;
        } else {
            $expr = self::$cache[$string][$objId];
        }
        
        return $expr;
        //return new Expression($exface, $string, $meta_object);
    }

    /**
     *
     * @param MetaAttributeInterface $attribute            
     * @return ExpressionInterface
     */
    public static function createFromAttribute(MetaAttributeInterface $attribute) : ExpressionInterface
    {
        $exface = $attribute->getObject()->getWorkbench();
        return self::createFromString($exface, $attribute->getAliasWithRelationPath(), $attribute->getRelationPath()->getStartObject());
    }
}
?>