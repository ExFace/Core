<?php
namespace exface\Core\QueryBuilders;

/**
 * SQL query builder for SAP HANA database
 *
 * This query builder is based on the MySQL syntax, which is mostly supported by SAP HANA.
 *
 * @author Andrej Kabachnik
 *        
 */
class SapHanaSqlBuilder extends MySqlBuilder
{

    /**
     * SAP HANA supports custom SQL statements in the GROUP BY clause.
     * The rest is similar to MySQL
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlGroupBy()
     */
    protected function buildSqlGroupBy(\exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart, $select_from = null)
    {
        $output = '';
        if ($this->checkForSqlStatement($qpart->getAttribute()->getDataAddress())) {
            if (is_null($select_from)) {
                $select_from = $qpart->getAttribute()->getRelationPath()->toString() ? $qpart->getAttribute()->getRelationPath()->toString() : $this->getMainObject()->getAlias();
            }
            $output = str_replace('[#alias#]', $select_from, $qpart->getAttribute()->getDataAddress());
        } else {
            $output = parent::buildSqlGroupBy($qpart, $select_from);
        }
        return $output;
    }
}
