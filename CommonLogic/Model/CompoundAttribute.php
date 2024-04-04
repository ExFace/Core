<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\CompoundAttributeComponentInterface;
use exface\Core\Interfaces\Model\CompoundAttributeInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class CompoundAttribute extends Attribute implements CompoundAttributeInterface
{
    private $components = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\CompoundAttributeInterface::addComponentAttribute()
     */
    public function addComponentAttribute(MetaAttributeInterface $attribute, string $valuePrefix, string $valueSuffix): CompoundAttributeInterface
    {
        $component = new CompoundAttributeComponent($this, $attribute, $valuePrefix, $valueSuffix);
        $this->components[] = $component;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\CompoundAttributeInterface::getComponents()
     * @return CompoundAttributeComponent[]
     */
    public function getComponents(): array
    {
        if ($this->components === null) {
            $this->getModel()->getModelLoader()->loadAttributeComponents($this);
        }
        return $this->components;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\CompoundAttributeInterface::splitValue()
     */
    public function splitValue(string $value) : array
    {
        $toSplit = $value;
        $values = [];
        $components = $this->getComponents();
        
        $firstPrefix = $components[0]->getValuePrefix();
        $lastSuffix = $this->getComponent(count($components)-1)->getValueSuffix();
        
        if ($firstPrefix !== '' && substr($toSplit, 0, strlen($firstPrefix)) === $firstPrefix) {
            //cut off the prefix of the first component (if exists)
            $toSplit = substr($toSplit, strlen($firstPrefix));
        }
        foreach ($this->getComponentDelimiters() as $delim) {
            //cut the value at the delimiters
            list($part, $toSplit) = explode($delim, $toSplit, 2);
            $values[] = $part;
        }
        if ($lastSuffix !== '') {
            //cut the remaining value at the last suffix (if exists) and at to values array
            list($part, $toSplit) = explode($lastSuffix, $toSplit, 2);
            $values[] = $part;           
        } else {
            //if last suffix doesnt exist add the remaining value to values array
            $values[] = $toSplit;
            $toSplit = null;
        }
        
        if ($toSplit !== null && $toSplit !== '') {
            throw new RuntimeException('Failed to split value "' . $value . '" of compound attribute "' . $this->getAliasWithRelationPath() . '" from object "' . $this->getObject()->getAliasWithNamespace() . '": non-empty remainder "' . $toSplit . '" after processing all components', '79G9JUB');
        }
        
        return $values;
    }
    
    /**
     * Returns array with delimiters for components. Delimiter consist of a component suffix and the next component's prefix.
     * Array does NOT contain prefix of first component and suffix of last component.
     *
     * @throws RuntimeException
     * @return array
     */
    public function getComponentDelimiters() : array
    {
        $components = $this->getComponents();
        $delims = [];
        foreach ($components as $idx => $comp) {
            $compNext = $components[$idx+1];
            if ($compNext === null) {
                break;
            }
            $delim = $comp->getValueSuffix() . $compNext->getValuePrefix();
            if ($delim !== '') {
                $delims[] = $delim;
            } else {
                throw new RuntimeException("Cannot split values of compound attribute '{$this->getAliasWithRelationPath()}' from object '{$this->getObject()->getAliasWithNamespace()}': could not find delimiter for compound component with sequence index {$idx}", '79G9JUB');
            }
        }
        return $delims;
    }
    
    /**
     * Merge the given values array to a compound value with the components prefixes and suffixes added
     * 
     * @param array $values
     * @throws RuntimeException
     * @return string
     */
    public function mergeValues(array $values) : string
    {
        $components = $this->getComponents();
        if (count($values) !== count($components)) {
            throw new RuntimeException("Cannont merge values for compound attribute '{$this->getAliasWithRelationPath()}' from object '{$this->getObject()->getAliasWithNamespace()}'. Different amount of values given than attribute has components!", '79G9JUB');
        }
        $mergedValue = '';
        foreach ($components as $idx => $comp) {
            $mergedValue .= $comp->getValuePrefix() . $values[$idx] . $comp->getValueSuffix();
        }
        return $mergedValue;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\CompoundAttributeInterface::splitCondition()
     */
    public function splitCondition(ConditionInterface $condition) : ConditionGroupInterface
    {
        if ($condition->getExpression()->isMetaAttribute() === false || $condition->getExpression()->getAttribute()->is($this) === false) {
            throw new InvalidArgumentException('Cannot split condition "' . $condition->toString() . '" for compound attribute "' . $this->getName() . '" (alias ' . $this->getAliasWithRelationPath() . ') from object "' . $this->getObject()->getAliasWithNamespace() . '": the condition is not based on this compound attribute!');
        }
        
        switch ($condition->getComparator()) {
            case ComparatorDataType::EQUALS:
            case ComparatorDataType::EQUALS_NOT:
                $group = ConditionGroupFactory::createAND($this->getObject(), $condition->willIgnoreEmptyValues());
                if ($condition->isEmpty() === false) {
                    $valueParts = $this->splitValue($condition->getValue());
                } else {
                    $valueParts = [];
                }
                foreach ($this->getComponents() as $idx => $comp) {
                    $group->addConditionFromString($comp->getAttribute()->getAliasWithRelationPath(), $valueParts[$idx], $condition->getComparator());
                }
                break;
            // In case of non-exact comparison the use might search for a part of the value.
            // So not all compound parts will be present. This is a bit complicated. 
            // If we have a compond value `xxx-yyy-z` the user might search search for 
            // `x`, `y` `xxx-y`, `x-y`, etc. For now we assume, that having exactly one
            // part like `x` means, it can be anywhere. If there are multiple parts,
            // they are assumed to start with the first one. Not sure, if this is a
            // a notable problem...
            case ComparatorDataType::IS:
            case ComparatorDataType::IS_NOT:
                $group = ConditionGroupFactory::createAND($this->getObject(), $condition->willIgnoreEmptyValues());
                if ($condition->isEmpty() === false) {
                    $valueParts = array_filter($this->splitValue($condition->getValue()), function($val){
                        return $val !== '' && $val !== null;
                    });
                } else {
                    $valueParts = [];
                }
                if (count($valueParts) === 1) {
                    $orGroup = ConditionGroupFactory::createOR($this->getObject(), $condition->willIgnoreEmptyValues());
                    foreach ($this->getComponents() as $idx => $comp) {
                        $orGroup->addConditionFromString($comp->getAttribute()->getAliasWithRelationPath(), $valueParts[0], $condition->getComparator());
                    }
                    $group->addNestedGroup($orGroup);
                } else {
                    foreach ($this->getComponents() as $idx => $comp) {
                        $group->addConditionFromString($comp->getAttribute()->getAliasWithRelationPath(), $valueParts[$idx], $condition->getComparator());
                    }
                }
                break;
            case ComparatorDataType::IN:
            case ComparatorDataType::NOT_IN:
                $values = is_array($condition->getValue()) ? $condition->getValue() : explode($this->getValueListDelimiter(), $condition->getValue());
                $newComparator = $condition->getComparator() === ComparatorDataType::IN ? ComparatorDataType::EQUALS : ComparatorDataType::EQUALS_NOT;
                // If it's an IN with only a single value, simply transform it to an EQUALS
                // compound IN (5-1) -> (comp1 = 5 AND comp2 = 1)
                if (count($values) === 1) {
                    $group = $this->splitCondition(ConditionFactory::createFromExpression($this->getWorkbench(), $condition->getExpression(), $condition->getValue(), $newComparator, $condition->willIgnoreEmptyValues()));
                } else {
                    // If the IN has multiple values, split each value as if it was an EQUALS and put them all
                    // into an OR-group: compound IN (5-1, 5-2) -> (comp1 = 5 AND comp2 = 1) OR (comp1 = 5 AND comp2 = 2)
                    $group = ConditionGroupFactory::createOR($this->getObject(), $condition->willIgnoreEmptyValues());
                    foreach ($values as $value) {
                        $valueCond = ConditionFactory::createFromExpression($this->getWorkbench(), $condition->getExpression(), $value, $newComparator, $condition->willIgnoreEmptyValues());
                        $valueCondGrp = $this->splitCondition($valueCond);
                        $group->addNestedGroup($valueCondGrp);
                    }
                }
                break;
            default:
                throw new RuntimeException('Cannot split condition "' . $condition->toString() . '" for compound attribute "' . $this->getAliasWithRelationPath() . '" from object "' . $this->getObject()->getAliasWithNamespace() . '": a generic split is not possible for comparator "' . $condition->getComparator() . '"!');
        }
        
        return $group;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\CompoundAttributeInterface::getComponent()
     */
    public function getComponent(int $index): CompoundAttributeComponentInterface
    {
        $comp = $this->getComponents()[$index];
        if ($comp === null) {
            throw new MetaAttributeNotFoundError($this->getObject(), 'Component "' . $index . '" not found for compound attribute "' . $this->getName() . '" (alias ' . $this->getAliasWithRelationPath() . ') from object "' . $this->getObject()->getAliasWithNamespace() . '"!');
        }
        return $comp;
    }
}
