<?php
namespace exface\Core\Tests\PHPUnit\Support\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilter;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\QueryBuilders\AbstractSqlBuilder;
use exface\Core\QueryBuilders\PostgreSqlBuilder;

/**
 * Exposes PostgreSQL date comparison internals for focused tests without booting the Workbench.
 */
class DateOnlyComparisonPostgreSqlBuilder extends PostgreSqlBuilder
{
    /**
     * Exposes day-interval predicate generation so boundary and dialect formatting can be tested directly.
     *
     * @param string $subject
     * @param string $comparator
     * @param mixed $value
     * @param bool $valueIsSQL
     * @param DataTypeInterface $dataType
     * @return string|null
     * @see AbstractSqlBuilder::buildSqlDateComparisonForDay()
     */
    public function buildDateOnlyComparison(
        string $subject,
        string $comparator,
        $value,
        bool $valueIsSQL,
        DataTypeInterface $dataType
    ) : ?string {
        return $this->buildSqlDateComparisonForDay($subject, $comparator, $value, $valueIsSQL, $dataType);
    }

    /**
     * Exposes the public comparison-building path so tests detect missing or misplaced helper integration.
     *
     * @param QueryPartAttribute $queryPart
     * @param string $subject
     * @param string $comparator
     * @param mixed $value
     * @return string
     * @see AbstractSqlBuilder::buildSqlWhereComparator()
     */
    public function buildWhereComparison(
        QueryPartAttribute $queryPart,
        string $subject,
        string $comparator,
        $value
    ) : string {
        return $this->buildSqlWhereComparator($queryPart, $subject, $comparator, $value, true);
    }

    /**
     * Exposes comparator optimization together with SQL generation to verify date IS semantics end to end.
     *
     * @param QueryPartFilter $queryPart
     * @param string $subject
     * @return string
     * @see AbstractSqlBuilder::getOptimizedComparator()
     * @see AbstractSqlBuilder::buildSqlWhereComparator()
     */
    public function buildOptimizedWhereComparison(QueryPartFilter $queryPart, string $subject) : string
    {
        return $this->buildSqlWhereComparator(
            $queryPart,
            $subject,
            $this->getOptimizedComparator($queryPart),
            $queryPart->getCompareValue(),
            true
        );
    }
}
