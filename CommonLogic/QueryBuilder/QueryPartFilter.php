<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\RelationPath;

/**
 * The filter query part represents one filter within a query (in SQL it translates to a WHERE-statement).
 * Filter query parts
 * implement the general filter interface and thus can be aggregated to filter groups with logical operators like AND, OR, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class QueryPartFilter extends QueryPartAttribute
{

    private $compare_value = null;

    private $comparator = null;

    private $condition = NULL;

    private $apply_after_reading = false;

    function __construct($alias, AbstractQueryBuilder $query)
    {
        parent::__construct($alias, $query);
        // If we filter over an attribute, which actually is a reverse relation, we need to explicitly tell the query, that
        // it is a relation and not a direct attribute. Concider the case of CUSTOMER<-CUSTOMER_CARD. If we filter CUSTOMERs over
        // CUSTOMER_CARD, it would look as if the CUSTOMER_CARD is an attribute of CUSTOMER. We need to detect this and transform
        // the filter into CUSTOMER_CARD__UID, which would clearly be a relation.
        if ($this->getAttribute()->isRelation() && $this->getQuery()->getMainObject()->getRelation($alias)->isReverseRelation()) {
            $attr = $this->getQuery()->getMainObject()->getAttribute(RelationPath::relationPathAdd($alias, $this->getAttribute()->getObject()->getUidAlias()));
            $this->setAttribute($attr);
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
     * @param Condition $condition            
     */
    public function setCondition(Condition $condition)
    {
        $this->condition = $condition;
        return $this;
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
        $this->apply_after_reading = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }
}
?>