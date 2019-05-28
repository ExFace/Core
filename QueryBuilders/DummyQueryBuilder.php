<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 * A query builder stub for non-interactive data sources, that cannot actuall do anything.
 * 
 * Sometimes, a data source is needed to save addresses or credentials, but not
 * for reading or writing data. In this case, this dummy query builder can be used to
 * make sure, no read/write attempts can be made resulting from misconfigurations
 * in the metamodel.
 * 
 * @author Andrej Kabachnik
 *        
 */
class DummyQueryBuilder extends AbstractQueryBuilder
{
    public function canReadAttribute(MetaAttributeInterface $attribute): bool
    {
        return false;
    }
}