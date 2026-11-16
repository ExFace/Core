<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Instantiates expression objects from string.
 * 
 * This factory reuses live expressions within each workbench to avoid repeated parsing,
 * particularly formula tokenization. Both workbench keys and expression values are weak references.
 *
 * Cached expressions expire or are replaced in these cases:
 * - No other code references an expression anymore: it can be garbage-collected. Its empty
 *   weak-reference entry remains until the same key is requested again or the workbench is collected.
 * - The same key is requested with a different metaobject instance, even with the same UID:
 *   the cached reference is replaced. This covers objects replaced after a model-cache clear or reload.
 * - A cached expression's metaobject was changed: requesting its original key replaces the reference
 *   if the expression is no longer bound to the requested instance.
 * - A workbench is garbage-collected: its entire cache partition is removed automatically.
 *   Live expressions still hold their workbench, so they must also be released first.
 *
 * Clearing the model cache does not explicitly clear this cache. The metaobject-instance check
 * prevents reuse against replacement objects, but existing expressions held by callers are not
 * refreshed or invalidated.
 */
abstract class ExpressionFactory
{
    /** @var \WeakMap<WorkbenchInterface, array<string, array<string, \WeakReference<ExpressionInterface>>>>|null */
    private static ?\WeakMap $cache = null;

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
        $objKey = $object === null ? 'null' : $object->getId()  . '-' . $treatUnknownAsString;
        $exprKey = static::getCacheKey($scalar);
        $expr = static::getCache($exface, $exprKey, $objKey);
        if ($expr === null || $expr->getMetaObject() !== $object) {
            $expr = new Expression($exface, $scalar, $object, true, $treatUnknownAsString);
            static::setCache($exface, $exprKey, $objKey, $expr);
        }
        
        return $expr;
    }

    /**
     * Builds an expression cache key that distinguishes scalar types.
     *
     * @param mixed $scalar
     * @return string
     */
    private static function getCacheKey($scalar) : string
    {
        // Values such as 0, false and '0' must not share a cache key.
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
        return $type . ':' . $string;
    }

    /**
     * Returns a live cached expression from the given workbench.
     *
     * @param WorkbenchInterface $workbench
     * @param string $expressionKey
     * @param string $objectKey
     * @return ExpressionInterface|null
     */
    private static function getCache(WorkbenchInterface $workbench, string $expressionKey, string $objectKey) : ?ExpressionInterface
    {
        $cached = self::$cache[$workbench][$expressionKey][$objectKey] ?? null;
        return $cached === null ? null : $cached->get();
    }

    /**
     * Caches an expression without retaining it or its workbench.
     *
     * @param WorkbenchInterface $workbench
     * @param string $expressionKey
     * @param string $objectKey
     * @param ExpressionInterface $expression
     * @return void
     */
    private static function setCache(WorkbenchInterface $workbench, string $expressionKey, string $objectKey, ExpressionInterface $expression) : void
    {
        self::$cache ??= new \WeakMap();
        if (! isset(self::$cache[$workbench])) {
            self::$cache[$workbench] = [];
        }
        self::$cache[$workbench][$expressionKey][$objectKey] = \WeakReference::create($expression);
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
    public static function createForObject(MetaObjectInterface $object, string $expression, bool $treatUnknownAsString = false) : ExpressionInterface
    {
        return static::createFromString($object->getWorkbench(), $expression, $object, $treatUnknownAsString);
    }
}
?>