<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Interfaces\Model\CompoundAttributeInterface;

class QueryPartSorter extends QueryPartAttribute
{

    private $order;

    private $apply_after_reading = false;

    public function __construct($alias, AbstractQueryBuilder $query, QueryPart $parentQueryPart = null) {
        parent::__construct($alias, $query, $parentQueryPart);
        
        if ($this->getAttribute() instanceof CompoundAttributeInterface) {
            // TODO #compound-attributes what if the compound has an aggregator? In the case of SelectQueryPart this is handled separately
            foreach ($this->getAttribute()->getComponents() as $comp) {
                // TODO #compound-attributes getAliasWithRelationPath() must include compound's relation path for
                // every component
                $compAlias = RelationPath::join($this->getAttribute()->getRelationPath()->toString(), $comp->getAttribute()->getAlias());
                $qpart = new self($compAlias, $query, $this);
                $query->addQueryPart($qpart);
                $this->addChildQueryPart($qpart);
            }
        }
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder($value)
    {
        if (! $value) {
            $value = 'ASC';
        }
        $this->order = $value;
        if ($this->isCompound()) {
            foreach ($this->getCompoundChildren() as $child) {
                $child->setOrder($value);
            }
        }
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
     * @return QueryPartSorter
     */
    public function setApplyAfterReading($value)
    {
        $this->apply_after_reading = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }
}