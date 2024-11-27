<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Interfaces\Model\CompoundAttributeInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\CommonLogic\DataSheets\DataAggregation;

class QueryPartSelect extends QueryPartAttribute
{

    private $column_key = null;
    
    private $excludeFromResult = false;
    
    private $usedInPlaceholders = false;
    
    public function __construct($alias, AbstractQueryBuilder $query, QueryPart $parentQueryPart = null, string $column_name = null) {
        parent::__construct($alias, $query, $parentQueryPart);
        $this->column_key = $column_name ?? DataColumn::sanitizeColumnName($alias);
        
        if ($this->getAttribute() instanceof CompoundAttributeInterface) {
            if ($this->hasAggregator() === true) {
                switch ($this->getAggregator()->getFunction()->__toString()) {
                    case AggregatorFunctionsDataType::COUNT:
                        $comp = $this->getAttribute()->getComponents()[0];
                        $compAlias = RelationPath::join($this->getAttribute()->getRelationPath()->toString(), $comp->getAttribute()->getAlias());
                        $compAlias = DataAggregation::addAggregatorToAlias($compAlias, $this->getAggregator());
                        $qpart = new self($compAlias, $query, $this);
                        $query->addQueryPart($qpart);
                        $this->addChildQueryPart($qpart);
                        break;
                    default:
                        throw new RuntimeException('Cannot read compound attributes with aggregator' . $this->getAggregator()->exportString() . '!');
                }
            } else {
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
    }
    
    public function isValid()
    {
        if ($this->getAttribute()->getDataAddress() != '') {
            return true;
        } elseif (empty($this->getAttribute()->getDataAddressProperties()) === false) {
            return true;
        }
        return false;
    }
    
    public function getColumnKey() : string
    {
        return $this->column_key;
    }
    
    /**
     * 
     * @return bool
     */
    public function isExcludedFromResult() : bool
    {
        return $this->excludeFromResult;
    }
    
    /**
     * 
     * @param bool $value
     * @return QueryPartSelect
     */
    public function excludeFromResult(bool $value) : QueryPartSelect
    {
        $this->excludeFromResult = $value;
        return $this;
    }
    
    /**
     * Returns TRUE if this query part was marked as required for data address placeholders.
     * 
     * @return bool
     */
    public function isUsedInPlaceholders() : bool
    {
        return $this->usedInPlaceholders;
    }
    
    /**
     * Marks this query part as required for data address placeholders.
     * 
     * This is important for query builders to know, which attributes MUST be read even if not
     * selected for the query (but still required to run it).
     * 
     * @param bool $value
     * @return QueryPartSelect
     */
    public function setUsedInPlaceholders(bool $value) : QueryPartSelect
    {
        $this->usedInPlaceholders = $value;
        return $this;
    }
}