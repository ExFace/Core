<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

abstract class ExpressionFactory
{

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
    public static function createFromString(Workbench $exface, $string, $meta_object = null)
    {
        return new Expression($exface, $string, $meta_object);
    }

    /**
     *
     * @param MetaAttributeInterface $attribute            
     * @return ExpressionInterface
     */
    public static function createFromAttribute(MetaAttributeInterface $attribute)
    {
        $exface = $attribute->getObject()->getWorkbench();
        return self::createFromString($exface, $attribute->getAliasWithRelationPath(), $attribute->getRelationPath()->getStartObject());
    }
}
?>