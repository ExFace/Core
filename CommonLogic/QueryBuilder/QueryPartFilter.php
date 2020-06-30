<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\Model\CompoundAttributeInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Model\ConditionInterface;

/**
 * The filter query part represents one filter within a query (in SQL it translates to a WHERE-statement).
 * Filter query parts
 * implement the general filter interface and thus can be aggregated to filter groups with logical operators like AND, OR, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class QueryPartFilter extends QueryPartAttribute implements iCanBeCopied
{
    private $compare_value = null;

    private $comparator = null;

    private $condition = NULL;

    private $apply_after_reading = false;
    
    private $value_is_data_address = false;
    
    private $compoundFilterGroup = null;

    function __construct($alias, AbstractQueryBuilder $query, ConditionInterface $condition, QueryPart $parentQueryPart = null)
    {
        parent::__construct($alias, $query, $parentQueryPart);
        $this->condition = $condition;
        // If we filter over an attribute, which actually is a reverse relation, we need to explicitly tell the query, that
        // it is a relation and not a direct attribute. Concider the case of CUSTOMER<-CUSTOMER_CARD. If we filter CUSTOMERs over
        // CUSTOMER_CARD, it would look as if the CUSTOMER_CARD is an attribute of CUSTOMER. We need to detect this and transform
        // the filter into CUSTOMER_CARD__UID, which would clearly be a relation.
        if ($this->getAttribute()->isRelation() && $this->getQuery()->getMainObject()->getRelation($alias)->isReverseRelation()) {
            $attr = $this->getQuery()->getMainObject()->getAttribute(RelationPath::relationPathAdd($alias, $this->getAttribute()->getObject()->getUidAttributeAlias()));
            $this->setAttribute($attr);
        }
        
        if ($this->getAttribute() instanceof CompoundAttributeInterface) {
            if ($this->hasAggregator() === true) {
                throw new RuntimeException('Cannot filter compound attributes with aggregators!');
            }
            $this->addChildQueryPart($this->getCompoundFilterGroup());
        }
    }

    /**
     *
     * @return string|mixed|NULL
     */
    public function getCompareValue()
    {
        if (! $this->compare_value)
            $this->compare_value = $this->getCondition()->getValue();
        return $this->compare_value;
    }

    /**
     *
     * @param mixed $value            
     */
    public function setCompareValue($value)
    {
        $this->compare_value = trim($value);
        return $this;
    }

    /**
     * Returns the comparator - one of the EXF_COMPARATOR_xxx constants
     *
     * @return string
     */
    public function getComparator()
    {
        if (! $this->comparator)
            $this->comparator = $this->getCondition()->getComparator();
        return $this->comparator;
    }

    /**
     * Sets the comparator - one of the EXF_COMPARATOR_xxx constants
     *
     * @param string $value            
     * @return QueryPartFilter
     */
    public function setComparator($value)
    {
        $this->comparator = $value;
        return $this;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Model\Condition
     */
    public function getCondition()
    {
        return $this->condition;
    }
    
    /**
     * Returns the delimiter to be used for concatennated value strings 
     * (comma by default)
     * 
     * @return string
     */
    public function getValueListDelimiter(){
        return $this->getAttribute()->getValueListDelimiter();
    }

    /**
     *
     * @return boolean
     */
    public function getApplyAfterReading()
    {
        return $this->apply_after_reading;
    }

    /**
     *
     * @param boolean $value            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilter
     */
    public function setApplyAfterReading($value)
    {
        $this->apply_after_reading = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     * Tells the filter to treat the value as a data address instead of a scalar data type.
     * 
     * This is important for non-string data types that would produce casting errors when
     * generating the query: e.g. filtering over an SQL column with numeric foreign keys,
     * you can compare either to a number or an SQL statement resulting in a number. The
     * query builder would attempt to cast the sql statement to the data type of the
     * attribute in the metamodel and will fail because the SQL itself is a string. 
     * 
     * @param boolean $true_or_false
     * 
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilter
     */
    public function setValueIsDataAddress($true_or_false) 
    {
        $this->value_is_data_address = $true_or_false;
        return $this;
    }
    
    /**
     * Returns TRUE if the value compared to is a data address and not a scalar value.
     * 
     * @return boolean
     */
    public function isValueDataAddress()
    {
        return $this->value_is_data_address;
    }

    /**
     * 
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilter
     */
    public function copy()
    {
        $copy = clone $this;
        $copy->condition = $this->getCondition()->copy();
        return $copy;
    }
    
    public function getCompoundFilterGroup() : QueryPartFilterGroup
    {
        if ($this->compoundFilterGroup === null) {
            if (($this->getAttribute() instanceof CompoundAttributeInterface) === false) {
                throw new RuntimeException('TODO');
            }
            
            $compoundFilterGroup = $this->getAttribute()->splitCondition($this->getCondition());
            $this->compoundFilterGroup = QueryPartFilterGroup::createQueryPartFromConditionGroup($compoundFilterGroup, $this->getQuery(), $this);
        }
        return $this->compoundFilterGroup;
    }
}
?>