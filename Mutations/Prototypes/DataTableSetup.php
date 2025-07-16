<?php
namespace exface\Core\Mutations\Prototypes;

use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Mutations\AppliedMutation;
use exface\Core\Widgets\DataTable;

/**
 * Allows to modify the UXON configuration of an objects action
 *
 * @author Andrej Kabachnik
 */
class DataTableSetup extends AbstractMutation
{
    private array $columnRules = [];
    private ?UxonObject $columnUxon = null;
    private array $filterRules = [];
    private ?UxonObject $filterUxon = null;
    private array $sorterRules = [];
    private ?UxonObject $sorterUxon = null;

    /**
     * @see MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        if (! $this->supports($subject)) {
            throw new InvalidArgumentException('Cannot apply page mutation to ' . get_class($subject) . ' - only DataTable widgets supported!');
        }

        return new AppliedMutation($this, $subject, '', '');
    }

    /**
     * @see MutationInterface::supports()
     */
    public function supports($subject): bool
    {
        return $subject instanceof DataTable;
    }

    /**
     * Setup for every column
     *
     * @uxon-property columns
     * @uxon-type \exface\Core\Mutations\MutationRules\DataColumnSetupRule[]
     * @uxon-template [{"attribute_alias": "", "show": true}]
     *
     * @param UxonObject $uxonArray
     * @return $this
     */
    protected function setColumns(UxonObject $uxonArray) : DataTableSetup
    {
        $this->columnUxon = $uxonArray;
        return $this;
    }

    /**
     * Setup for filters to modify
     *
     * @uxon-property filters
     * @uxon-type \exface\Core\Mutations\MutationRules\FilterSetupRule[]
     * @uxon-template [{"operator": "AND", "conditions": [{"attribute_alias": "", "comparator": "", "value": ""}]}]
     *
     * @param UxonObject $uxonArray
     * @return $this
     */
    protected function setFilters(UxonObject $uxonArray) : DataTableSetup
    {
        $this->filterUxon = $uxonArray;
        return $this;
    }

    /**
     * Setup for sorters to modify
     *
     * @uxon-property sorters
     * @uxon-type \exface\Core\Mutations\MutationRules\SorterSetupRule[]
     * @uxon-template [{"attribute_alias": "", "direction": ""}]
     *
     * @param UxonObject $uxonArray
     * @return $this
     */
    protected function setSorters(UxonObject $uxonArray) : DataTableSetup
    {
        $this->sorterUxon = $uxonArray;
        return $this;
    }
}