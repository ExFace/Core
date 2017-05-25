<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\Factories\ConditionGroupFactory;

/**
 * A filter group query part represents a condition group used for filtering in a query.
 * 
 * @author Andrej Kabachnik
 *        
 */
class QueryPartFilterGroup extends QueryPart
{

    private $operator = EXF_LOGICAL_AND;

    private $filters = array();

    private $nested_groups = array();

    private $condition_group = null;

    public function getOperator()
    {
        return $this->operator;
    }

    public function setOperator($value)
    {
        $this->operator = $value;
    }

    /**
     * Adds a filter to the group.
     * 
     * @param
     *            QueryPartFilter
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
     */
    public function addFilter(QueryPartFilter $filter)
    {
        // Only add filters based on attributes. A query can only work with meta model attributes, not with other
        // expressions. Filters based on formulas need to be applied by the DataSheet and cannot be handled by queries!
        if ($filter->getAttribute() && ! is_null($filter->getCompareValue()) && $filter->getCompareValue() !== '') {
            $this->filters[] = $filter;
            $this->getConditionGroup()->addCondition($filter->getCondition());
        }
        return $this;
    }

    /**
     * Creates a filter from a given condition object and adds it to the group
     * 
     * @param Condition $condition            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
     */
    public function addCondition(Condition $condition)
    {
        $this->addFilter($this->createQueryPartFromCondition($condition));
        return $this;
    }

    /**
     * Adds a nested filter group.
     * 
     * @param QueryPartFilterGroup $group            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
     */
    public function addNestedGroup(QueryPartFilterGroup $group)
    {
        $this->nested_groups[] = $group;
        $this->getConditionGroup()->addNestedGroup($group->getConditionGroup());
        return $this;
    }

    /**
     * Creates a filter group from a given condition group and adds it to the group
     * 
     * @param ConditionGroup $group            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
     */
    public function addConditionGroup(ConditionGroup $group)
    {
        $this->addNestedGroup($this->createQueryPartFromConditionGroup($group));
        return $this;
    }

    /**
     *
     * @return QueryPartFilter[]
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     *
     * @return QueryPartFilterGroup[]
     */
    public function getNestedGroups()
    {
        return $this->nested_groups;
    }

    /**
     * Returns an array of filters an nested filter groups - that is, all query parts contained in this filter group
     * 
     * @return QueryPart[]
     */
    public function getFiltersAndNestedGroups()
    {
        return array_merge($this->getFilters(), $this->getNestedGroups());
    }

    /**
     * Creates a filter query part from a condition
     * 
     * @param Condition $condition            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilter
     */
    public function createQueryPartFromCondition(Condition $condition)
    {
        $qpart = new QueryPartFilter($condition->getExpression()->toString(), $this->query);
        $qpart->setCondition($condition);
        return $qpart;
    }

    /**
     * Creates a filter group query part from a condition group
     * 
     * @param ConditionGroup $group            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
     */
    public function createQueryPartFromConditionGroup(ConditionGroup $group)
    {
        $qpart = new QueryPartFilterGroup('', $this->query);
        $qpart->setOperator($group->getOperator());
        foreach ($group->getConditions() as $c) {
            $qpart->addCondition($c);
        }
        foreach ($group->getNestedGroups() as $g) {
            $qpart->addConditionGroup($g);
        }
        return $qpart;
    }

    /**
     * A filter group uses all relations used by it's filters and subgroups
     * 
     * @see \exface\Core\CommonLogic\QueryBuilder\QueryPart::getUsedRelations()
     */
    public function getUsedRelations($relation_type = null)
    {
        $rels = array();
        foreach ($this->getFilters() as $qpart) {
            $rels = array_merge($rels, $qpart->getUsedRelations($relation_type));
        }
        foreach ($this->getNestedGroups() as $qpart) {
            $rels = array_merge($rels, $qpart->getUsedRelations($relation_type));
        }
        return $rels;
    }

    /**
     * Returns an array of meta object ids, that are assumed to be unique in the result of the query because of filtering for
     * a single instance of that meta object.
     * 
     * @return array
     */
    public function getObjectIdsSafeForAggregation()
    {
        $ids = array();
        foreach ($this->getFilters() as $qpart) {
            // TODO The current checks do not really ensure, that the object is unique. Need a better idea!
            if ($qpart->getComparator() == EXF_COMPARATOR_IS || $qpart->getComparator() == EXF_COMPARATOR_EQUALS) {
                $ids[] = ($qpart->getAttribute()->isRelation() ? $this->getQuery()
                    ->getMainObject()
                    ->getRelatedObject($qpart->getAlias())
                    ->getId() : $qpart->getAttribute()
                    ->getObject()
                    ->getId());
            }
        }
        foreach ($this->getNestedGroups() as $qpart) {
            $ids = array_merge($ids, $qpart->getObjectIdsSafeForAggregation());
        }
        return $ids;
    }

    /**
     * Returns the condition group represented by this filter group.
     *
     * IDEA Currently the condition group is updated every time something happens to the filter group (add_filter(), add_nested_group(), etc.). Perhaps it
     * is a better idea to build the condition group on demand, because it is only needed on very rare occasions (e.g. for rebasing conditions in subqueries, etc.)
     * I don't know, if maintainig the condition group all the time has an impact on performance or memory consumption.
     *
     * @return \exface\Core\CommonLogic\Model\ConditionGroup
     */
    public function getConditionGroup()
    {
        $exface = $this->getWorkbench();
        if (! $this->condition_group)
            $this->condition_group = ConditionGroupFactory::createEmpty($exface, $this->getOperator());
        return $this->condition_group;
    }

    /**
     * Returns a filter query part matching the given alias or FALSE if no match found.
     * Checks nested filter groups recursively.
     * 
     * @param string $alias            
     * @return QueryPartFilter || boolean
     */
    public function findFilterByAlias($alias)
    {
        foreach ($this->getFilters() as $f) {
            if ($f->getAlias() == $alias)
                return $f;
        }
        
        foreach ($this->getNestedGroups() as $g) {
            if ($f = $g->findFilterByAlias($alias))
                return $f;
        }
        
        return false;
    }
}
?>