<?php
namespace exface\Core\Mutations\MutationRules;

use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Mutations\AppliedMutation;
use exface\Core\Widgets\DataColumn;

/**
 * Allows to modify the UXON configuration of an objects action
 *
 * @author Andrej Kabachnik
 */
class FilterSetupRule extends AbstractMutation
{
    private ?string $expression = null;
    private ?bool $value = null;

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
     * Preset value for the filter
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
}