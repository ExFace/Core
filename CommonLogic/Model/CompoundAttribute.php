<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\CompoundAttributeInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Exceptions\InvalidArgumentException;

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
        $delim = '';
        $toSplit = $value;
        $values = [];
        $components = $this->getComponents();
        
        $firstPrefix = $components[0]->getValuePrefix();
        if ($firstPrefix !== '' && substr($toSplit, 0, strlen($firstPrefix)) === $firstPrefix) {
            $toSplit = substr($toSplit, strlen($firstPrefix));
        }
        
        foreach ($components as $idx => $comp) {
            $compNext = $components[$idx+1];
            if ($compNext === null) {
                $lastSuffix = $comp->getValueSuffix();
                if ($lastSuffix !== '' && substr($toSplit, 0, strlen($lastSuffix)) === $lastSuffix) {
                    $toSplit = substr($toSplit, 0, (-1)*strlen($lastSuffix));
                }
                $values[] = $toSplit;
                $toSplit = null;
                break;
            }
            $delim = $comp->getValueSuffix() . $compNext->getValuePrefix();
            if ($delim !== '') {
                list($part, $toSplit) = explode($delim, $toSplit, 2);
                $values[] = $part;
            } else {
                throw new RuntimeException('Cannot split value "' . $value . '" of compound attribute "' . $this->getAliasWithRelationPath() . '": could not find delimiter for compound component with sequence index ' . $idx, '79G9JUB');
            }
        }
        
        if ($toSplit !== null) {
            throw new RuntimeException('Failed to split value "' . $value . '" of compound attribute "' . $this->getAliasWithRelationPath() . '": non-empty remainder "' . $toSplit . '" after processing all components', '79G9JUB');
        }
        
        return $values;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\CompoundAttributeInterface::splitCondition()
     */
    public function splitCondition(ConditionInterface $condition) : ConditionGroupInterface
    {
        if ($condition->getExpression()->isMetaAttribute() === false || $condition->getExpression()->getAttribute()->is($this) === false) {
            throw new InvalidArgumentException('Cannot split condition "' . $condition->toString() . '" for compound attribute "' . $this->getName() . '" (alias ' . $this->getAliasWithRelationPath() . '): the condition is not based on this compound attribute!');
        }
        
        $group = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_AND, $this->getObject());
        
        if ($condition->isEmpty() === false) {
            $valueParts = $this->splitValue($condition->getValue());
        } else {
            $valueParts = [];
        }
        
        switch ($condition->getComparator()) {
            case ComparatorDataType::EQUALS:
            case ComparatorDataType::EQUALS_NOT:
            case ComparatorDataType::IS:
            case ComparatorDataType::IS_NOT:
                foreach ($this->getComponents() as $idx => $comp) {
                    $group->addConditionFromString($comp->getAttribute()->getAliasWithRelationPath(), $valueParts[$idx], $condition->getComparator());
                }
                break;
            case ComparatorDataType::IN:
            case ComparatorDataType::NOT_IN:
                $values = is_array($condition->getValue()) ? $condition->getValue() : explode($this->getValueListDelimiter(), $condition->getValue());
                if (count($values) === 1) {
                    $newComparator = $condition->getComparator() === ComparatorDataType::IN ? ComparatorDataType::EQUALS : ComparatorDataType::EQUALS_NOT;
                    return $this->splitCondition(ConditionFactory::createFromExpression($this->getWorkbench(), $condition->getExpression(), $condition->getValue(), $newComparator));
                }
                
                // TODO transform IN-conditions into lot's of ANDs and ORs
                //break;
            default:
                throw new RuntimeException('Cannot split condition "' . $condition->toString() . '" for compound attribute "' . $this->getAliasWithRelationPath() . '": a generic split is not possible for comparator "' . $condition->getComparator() . '"!');
        }
        
        return $group;
    }
}