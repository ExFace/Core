<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\CommonLogic\Model\CompoundAttributeComponent;
use exface\Core\Exceptions\RuntimeException;

/**
 * 
 *
 * @author Andrej Kabachnik
 *
 */
interface CompoundAttributeInterface extends MetaAttributeInterface
{
    /**
     * 
     * @return CompoundAttributeComponentInterface[]
     */
    public function getComponents() : array;
    
    /**
     * 
     * @param MetaAttributeInterface $attribute
     * @return CompoundAttributeInterface
     */
    public function addComponentAttribute(MetaAttributeInterface $attribute, string $valuePrefix, string $valueSuffix) : CompoundAttributeInterface;
    
    /**
     * Returns an associative array with component indexes as keys and the respective value parts.
     * 
     * E.g. if you we have a compound order position id attribute, that consists of an order number and a 
     * position index (with `--` as prefix), this method the value `123--1` into an `[123, 1]`.
     * 
     * @param string $value
     * @throws RuntimeException if the given value cannot be split
     * @return array
     */
    public function splitValue(string $value) : array;
    
    /**
     * Transforms a condition based on the compound attribute into a condition group over it's components.
     * 
     * E.g. if you we have a compound order position id attribute, that consists of an order number and a 
     * position index (with `--` as prefix), this method will transform the condition `POS_ID == 123--1`
     * into `AND(ORDER_ID == 123, POS_IDX == 1)`.
     * 
     * @param ConditionInterface $condition
     * @return ConditionGroupInterface
     */
    public function splitCondition(ConditionInterface $condition) : ConditionGroupInterface;
}