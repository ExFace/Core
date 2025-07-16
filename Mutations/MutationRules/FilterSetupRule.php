<?php
namespace exface\Core\Mutations\MutationRules;

use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Mutations\AppliedMutation;
use exface\Core\Widgets\DataColumn;
use exface\Core\CommonLogic\UxonObject;

/**
 * Allows to modify the UXON configuration of an objects action
 *
 * @author Andrej Kabachnik
 */
class FilterSetupRule extends AbstractMutation
{
    private ?string $expression = null;
    private ?bool $value = null;
    private ?string $operator = null;
    private ?string $comparator = null;
    private array $conditions = [];

    /**
     * @see MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        if (! $this->supports($subject)) {
            throw new InvalidArgumentException('Cannot apply page mutation to ' . get_class($subject) . ' - only DataColumn widgets supported!');
        }

        return new AppliedMutation($this, $subject, '', '');
    }

    /**
     * @see MutationInterface::supports()
     */
    public function supports($subject): bool
    {
        return $subject instanceof DataColumn;
    }

    /**
     * Target filter attribute alias - used to identify the filter to set up
     *
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $attributeAlias
     * @return $this
     */
    protected function setAttributeAlias(string $attributeAlias): FilterSetupRule
    {
        $this->expression = $attributeAlias;
        return $this;
    }

    /**
     * Comparator value for the filter
     *
     * @uxon-property value
     * @uxon-type string
     *
     * @param string|bool|int|float $trueOrFalse
     * @return $this
     */
    protected function setValue($value): FilterSetupRule
    {
        $this->value = $value;
        return $this;
    }

    /**
     * The comparator action of the filter (==, !=, etc.)
     *
     * @uxon-property comparator
     * @uxon-type string
     *
     * @param string|bool|int|float $trueOrFalse
     * @return $this
     */
    protected function setComparator($comparator): FilterSetupRule
    {
        $this->comparator = $comparator;
        return $this;
    }


    /**
     * The operator that chains the conditions together (AND is default in DataTableConfigurators)
     *
     * @uxon-property operator
     * @uxon-type string
     *
     * @param string|bool|int|float $trueOrFalse
     * @return $this
     */
    protected function setOperator($operator): FilterSetupRule
    {
        $this->operator = $operator;
        return $this;
    }

    /**
     * Array of conditions to apply
     *
     * @uxon-property conditions
     * @uxon-type array
     *
     * @param UxonObject $uxonArray
     * @return $this
     */
    protected function setConditions(UxonObject $uxonArray): FilterSetupRule
    {
        $this->conditions = $uxonArray->toArray();
        return $this;
    }
}