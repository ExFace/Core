<?php
namespace exface\Core\Mutations\Prototypes;

use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Mutations\AppliedMutation;
use exface\Core\Widgets\DataTable;

/**
 * User-side mutation (widget setup) for DataTable widgets - to allow users to personalize tables and save their setups.
 * 
 * 
 *
 * @author Andrej Kabachnik
 */
class DataTableSetup extends AbstractMutation implements WidgetSetupInterface
{
    private ?UxonObject $columnUxon = null;
    private ?UxonObject $searchUxon = null;
    private ?UxonObject $advancedConditionsUxon = null;
    private ?UxonObject $sorterUxon = null;

    /**
     * @see MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        if (! $this->supports($subject)) {
            throw new InvalidArgumentException('Cannot apply page mutation to ' . get_class($subject) . ' - only DataTable widgets supported!');
        }
        
        // TODO implement the logic to apply the setup in PHP. Currently it is only applied in JS, but the idea is
        // to make it applicable in most worlds to allow setups in mutations too (for example, when extending a widget).

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
     * Setup for advanced search filters to modify
     *
     * @uxon-property advanced_search
     * @uxon-type \exface\Core\Mutations\MutationRules\FilterSetupRule[]
     * @uxon-template [{"attribute_alias": "", "comparator": "", "value": "", "exclude": false}]
     *
     * @param UxonObject $uxonArray
     * @return $this
     */
    protected function setAdvancedSearch(UxonObject $uxonArray) : DataTableSetup
    {
        $this->searchUxon = $uxonArray;
        return $this;
    }

    /**
     * Complete condition group configured in Advanced Search.
     *
     * @uxon-property advanced_conditions
     * @uxon-type \exface\Core\Mutations\MutationRules\AdvancedConditionGroupSetupRule
     * @uxon-template {"operator": "AND", "ignore_empty_values": true, "conditions": [], "nested_groups": []}
     *
     * @param UxonObject $conditionGroupUxon
     * @return $this
     */
    protected function setAdvancedConditions(UxonObject $conditionGroupUxon) : DataTableSetup
    {
        $this->advancedConditionsUxon = $conditionGroupUxon;
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