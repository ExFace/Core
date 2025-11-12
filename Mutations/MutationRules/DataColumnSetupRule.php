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
class DataColumnSetupRule extends AbstractMutation
{
    private ?string $expression = null;
    private ?bool $show = null;
    private ?string $width = null;
    private array $changes = [];

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
     * Target column attribute alias - used to identify the column to set up
     *
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $attributeAlias
     * @return $this
     */
    protected function setAttributeAlias(string $attributeAlias): DataColumnSetupRule
    {
        $this->expression = $attributeAlias;
        return $this;
    }

    /**
     * Custom column width set by user by resizing columns (e.g. '100px') 
     *
     * @uxon-property custom_width
     * @uxon-type string
     *
     * @param string $customWidth
     * @return $this
     */
    protected function setCustomWidth(string $customWidth): DataColumnSetupRule
    {
        $this->width = $customWidth;
        return $this;
    }

    /**
     * Target column name - used to identify the column to set up
     *
     * @uxon-property column_name
     * @uxon-type string
     *
     * @param string $column_name
     * @return $this
     */
    protected function setColumnName(string $column_name): DataColumnSetupRule
    {
        $this->expression = $column_name;
        return $this;
    }

    /**
     * Set to TRUE to show the column or FALSE to hide it
     *
     * @uxon-property show
     * @uxon-type boolean
     *
     * @param bool $trueOrFalse
     * @return $this
     */
    protected function setShow(bool $trueOrFalse): DataColumnSetupRule
    {
        $this->show = $trueOrFalse;
        return $this;
    }
}