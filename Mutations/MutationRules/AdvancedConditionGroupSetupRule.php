<?php
namespace exface\Core\Mutations\MutationRules;

use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Mutations\AppliedMutation;
use exface\Core\Widgets\DataTable;

/**
 * Stores one complete Advanced Search condition group for a DataTable setup.
 *
 * Conditions and nested groups use the regular ConditionGroup UXON structure, preserving logical
 * operators and arbitrarily nested Advanced Search groups.
 *
 * @author Andrej Kabachnik
 */
class AdvancedConditionGroupSetupRule extends AbstractMutation
{
    private ?string $operator = null;
    private ?bool $ignoreEmptyValues = null;
    private ?UxonObject $conditions = null;
    private ?UxonObject $nestedGroups = null;

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Mutations\MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        if (! $this->supports($subject)) {
            throw new InvalidArgumentException('Cannot apply Advanced Search setup to ' . get_class($subject) . ' - only DataTable widgets supported!');
        }

        return new AppliedMutation($this, $subject, '', '');
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Mutations\MutationInterface::supports()
     */
    public function supports($subject): bool
    {
        return $subject instanceof DataTable;
    }

    /**
     * Logical operator used to combine this group's conditions and nested groups.
     *
     * @uxon-property operator
     * @uxon-type [AND,OR,XOR]
     * @uxon-default AND
     *
     * @param string $operator
     * @return $this
     */
    protected function setOperator(string $operator): AdvancedConditionGroupSetupRule
    {
        $this->operator = $operator;
        return $this;
    }

    /**
     * Conditions contained directly in this group.
     *
     * @uxon-property conditions
     * @uxon-type \exface\Core\CommonLogic\Model\Condition[]
     * @uxon-template [{"expression": "", "comparator": "==", "value": ""}]
     *
     * @param UxonObject $conditions
     * @return $this
     */
    protected function setConditions(UxonObject $conditions): AdvancedConditionGroupSetupRule
    {
        $this->conditions = $conditions;
        return $this;
    }

    /**
     * Further condition groups contained in this group.
     *
     * @uxon-property nested_groups
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup[]
     * @uxon-template [{"operator": "AND", "conditions": []}]
     *
     * @param UxonObject $nestedGroups
     * @return $this
     */
    protected function setNestedGroups(UxonObject $nestedGroups): AdvancedConditionGroupSetupRule
    {
        $this->nestedGroups = $nestedGroups;
        return $this;
    }

    /**
     * Set to TRUE to ignore conditions whose value is empty.
     *
     * @uxon-property ignore_empty_values
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $trueOrFalse
     * @return $this
     */
    protected function setIgnoreEmptyValues(bool $trueOrFalse): AdvancedConditionGroupSetupRule
    {
        $this->ignoreEmptyValues = $trueOrFalse;
        return $this;
    }
}