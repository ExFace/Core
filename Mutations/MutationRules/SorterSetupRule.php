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
class SorterSetupRule extends AbstractMutation
{
    private ?string $expression = null;
    private ?bool $direction = null;

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
     * Target sorter attribute alias - used to identify the sorter to set up
     *
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $attributeAlias
     * @return $this
     */
    protected function setAttributeAlias(string $attributeAlias): SorterSetupRule
    {
        $this->expression = $attributeAlias;
        return $this;
    }

    /**
     * Sorter direction (Descending, Ascending)
     *
     * @uxon-property direction
     * @uxon-type string
     *
     * @param string|bool|int|float $direction
     * @return $this
     */
    protected function setDirection($direction): SorterSetupRule
    {
        $this->direction = $direction;
        return $this;
    }
}