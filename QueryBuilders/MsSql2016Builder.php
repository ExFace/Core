<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\CommonLogic\QueryBuilder\QueryPart;
use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilter;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Interfaces\Model\AggregatorInterface;

/**
 * A query builder for Microsoft SQL 2016.
 * 
 * Supported dialect tags in multi-dialect statements (in order of priority): 
 * - `@T-SQL2016:`, 
 * - `@MSSQL2016:`, 
 * - `@T-SQL:`, 
 * - `@MSSQL`, 
 * - `@OTHER`.
 * 
 * See `MsSqlBuilder` for more information.
 * 
 * Differences compared to the generic `MsSqlBuilder`:
 * 
 * - Added dialect `@T-SQL2016` in order to be able to write specific data addresses for 
 * SQL SERVER 2016 - e.g. without `TRIM()` function, etc.
 * 
 * @author Andrej Kabachnik
 *        
 */
class MsSql2016Builder extends MsSqlBuilder
{    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::getSqlDialects()
     */
    protected function getSqlDialects() : array
    {
        return array_merge(['T-SQL2016', 'MSSQL2016'], parent::getSqlDialects());
    }

    protected function needsForXml(QueryPartAttribute $qpart, ?AggregatorInterface $aggregator = null) : bool
    {
        $aggr = $aggregator ?? $qpart->getAggregator();
        if ($aggr && ($aggr->getFunction()->getValue() === AggregatorFunctionsDataType::LIST_DISTINCT || $aggr->getFunction()->getValue() === AggregatorFunctionsDataType::LIST_ALL)) {
            return true;
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlGroupByExpression()
     */
    protected function buildSqlGroupByExpression(QueryPartAttribute $qpart, $sql, AggregatorInterface $aggregator){
        $args = $aggregator->getArguments();
        $function_name = $aggregator->getFunction()->getValue();

        switch ($function_name) {
            case AggregatorFunctionsDataType::LIST_ALL:
            case AggregatorFunctionsDataType::LIST_DISTINCT:
                // This is a VERY strange way to concatennate row values, but it seems to be the only
                // one available in SQL Server: STUFF(CAST(( SELECT ... FOR XML PATH(''), TYPE) AS VARCHAR(max)), 1, {LengthOfDelimiter}, '')
                // Since in case of subselects the `...` needs to be replaced by the whole subselect,
                // we need to split the logic in two: `STUFF...` goes here and `FOR XML...` goes in
                // buildSqlSelectSubselect() or buildSqlSelectGrouped() for subselects and regular
                // columns a bit differently.

                // Make sure to cast any non-string things to nvarchar BEFORE they are concatenated
                if (! ($qpart->getAttribute()->getDataType() instanceof StringDataType)) {
                    $sql = 'CAST(' . $sql . ' AS nvarchar(max))';
                }
                $delim = $args[0] ?? $this->buildSqlGroupByListDelimiter($qpart);
                if ($qpart->getQuery()->isSubquery()) {
                    $qpart->getQuery()->addAggregation($qpart->getAttribute()->getAliasWithRelationPath());
                }
                return "STUFF(CAST(( SELECT " . ($function_name == 'LIST_DISTINCT' ? 'DISTINCT ' : '') . "[text()] = '{$this->escapeString($delim)}' + {$sql}";
            default:
                return parent::buildSqlGroupByExpression($qpart, $sql, $aggregator);
        }
    }

    protected function buildSqlWhereSubquery(QueryPartFilter $qpart, $rely_on_joins = true)
    {
        // This is a workaround for SQL errors due to unclosed FOR XML wrappers in HAVING clauses.
        // The problem occurs when filtering over an aggregated attribute, that has a relation path with
        // more than one reverse relation. In this case, the WHERE gets a nested subselect with a HAVING 
        // clause (not quite sure, if that is correct) and inside that clause there is `SELECT [text()] = `
        // but no `FOR XML`. 
        // The workaround simply removes the aggregator from the filter in this case. It produces different
        // results - e.g. the filter value cannot contain a delimited list, but only a single value. But it works
        // for a lot of cases - in particular for table columns with a filter in the header.
        if ($qpart->hasAggregator()) {
            $aggr = $qpart->getAggregator();
            $aggrFunc = $aggr->getFunction()->__toString();
            if ($aggrFunc === AggregatorFunctionsDataType::LIST_DISTINCT || $aggrFunc === AggregatorFunctionsDataType::LIST_DISTINCT) {
                $relPath = $qpart->getAttribute()->getRelationPath();
                $revRelCnt = 0;
                foreach ($relPath->getRelations() as $rel) {
                    if ($rel->isReverseRelation()) {
                        $revRelCnt++;
                    }
                }
                if ($revRelCnt > 1) {
                    $alias = DataAggregation::stripAggregator($qpart->getAlias());
                    $condUxon = $qpart->getCondition()->exportUxonObject();
                    $condAlias = $condUxon->getProperty('expression');
                    $condUxon->setProperty('expression', DataAggregation::stripAggregator($condAlias));
                    $condNoAggr = ConditionFactory::createFromUxon($this->getWorkbench(), $condUxon);
                    $qpartNoAggr = new QueryPartFilter($alias, $this, $condNoAggr);
                    return parent::buildSqlWhereSubquery($qpartNoAggr, $rely_on_joins);
                }
            }
        }

        // In all other cases, use the default SQL builder logic
        return parent::buildSqlWhereSubquery($qpart, $rely_on_joins);
    }
}