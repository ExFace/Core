<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Interfaces\Model\CompoundAttributeInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\RuntimeException;

class QueryPartSelect extends QueryPartAttribute
{

    private $column_key = null;
    
    public function __construct($alias, AbstractQueryBuilder $query, QueryPartAttribute $parentQueryPart = null, string $column_name = null) {
        parent::__construct($alias, $query, $parentQueryPart);
        $this->column_key = $column_name ?? DataColumn::sanitizeColumnName($alias);
        
        if ($this->getAttribute() instanceof CompoundAttributeInterface) {
            foreach ($this->getAttribute()->getComponents() as $comp) {
                if ($this->hasAggregator() === true) {
                    throw new RuntimeException('Cannot read compound attributes with aggregators!');
                }
                // TODO #compound-attributes getAliasWithRelationPath() must include compound's relation path for
                // every component
                $compAlias = RelationPath::relationPathAdd($this->getAttribute()->getRelationPath()->toString(), $comp->getAttribute()->getAlias());
                $qpart = new self($compAlias, $query, $this);
                $query->addQueryPart($qpart);
                $this->addChildQueryPart($qpart);
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
}
?>