<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Interfaces\DataSheets\DataColumnMappingInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\DataSheets\DataFilterToColumnMappingInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Maps all filters matching the given expression from one sheet to corresponding filters of another.
 * 
 * If `inherit_filters` is set in the mapper, matching filters will NOT be inherited by
 * default (because they are transformed to columns). If you want them to get inherited,
 * set `prevent_inheriting_filter` to `false` for this mapping.
 *  
 * @see DataColumnMappingInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataFilterToFilterMapping extends DataColumnMapping 
{
    private ?string $fromComparator = null;
    
    private ?string $toComparator = null;
    
    private bool $removeFilter = true;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {        
        $this->mapConditions($fromSheet->getFilters(), $toSheet->getFilters());
        return $toSheet;
    }

    /**
     *
     * @param ConditionGroupInterface $fromConditionGroup
     * @param ConditionGroupInterface $toConditionGroup
     * @return ConditionInterface[]
     */
    protected function mapConditions(ConditionGroupInterface $fromConditionGroup, ConditionGroupInterface $toConditionGroup, LogBookInterface $logbook = null) : array
    {
        $exprString = $this->getFromExpression()->__toString();
        $fromComparator = $this->getFromComparator();
        $toComparator = $this->getToComparator();
        $preventInheriting = $this->getPreventInheritingFilter();
        $result = [];
        
        foreach ($fromConditionGroup->getConditions() as $fromCondition) {
            $condComp = $fromCondition->getComparator();
            if (strcasecmp($fromCondition->getExpression()->__toString(), $exprString) === 0) {
                if ($fromComparator === $condComp || ($fromComparator === null || $fromComparator === '')) {
                    $result[] = $toConditionGroup->addConditionFromString(
                        $this->getToExpression()->__toString(), 
                        $fromCondition->getValue(), 
                        $toComparator ?? $fromCondition->getComparator(),
                        $fromCondition->willIgnoreEmptyValues()
                    );
                    if ($preventInheriting) {
                        $fromConditionGroup->removeCondition($fromCondition);
                    }
                }
            }
        }
        
        /* TODO what to do with nested groups?
        foreach ($fromConditionGroup->getNestedGroups() as $group) {
            $result = array_merge($result, $this->findFilterConditions($group, $fromExpression, $fromComparator));
        }*/
        
        return $result;
    }
    
    /**
     * @return string|NULL
     */
    protected function getFromComparator() : ?string
    {
        return $this->fromComparator;
    }

    /**
     * Take only filters with this comparator.
     * 
     * @uxon-property from_comparator
     * @uxon-type metamodel:comparator
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataFilterToColumnMappingInterface::setFromComparator()
     */
    public function setFromComparator(string $comparator) : DataFilterToColumnMappingInterface
    {
        $this->fromComparator = $comparator;
        return $this;
    }

    /**
     * @return string|NULL
     */
    protected function getToComparator() : ?string
    {
        return $this->toComparator;
    }

    /**
     * Use this comparator in the to-filter instead of the one used in the from-filter
     *
     * @uxon-property to_comparator
     * @uxon-type metamodel:comparator
     * 
     * @param string $comparator
     * @return DataFilterToColumnMappingInterface
     */
    public function setToComparator(string $comparator) : DataFilterToColumnMappingInterface
    {
        $this->toComparator = $comparator;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getPreventInheritingFilter() : bool
    {
        return $this->removeFilter;
    }
    
    /**
     * Set to FALSE if you want the to-sheet to inherit the filter if possible.
     * 
     * @uxon-property prevent_inheriting_filter
     * @uxon-type boolean
     * @uxon-default true
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataFilterToColumnMappingInterface::setPreventInheritingFilter()
     */
    public function setPreventInheritingFilter(bool $value) : DataFilterToColumnMappingInterface
    {
        $this->removeFilter = $value;
        return $this;
    }

    /**
     *
     * {@inheritDoc}
     * @see DataColumnMapping::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        return [];
    }
}