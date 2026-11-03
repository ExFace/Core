<?php
namespace exface\Core\Tests\PHPUnit\Support\QueryBuilders;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\QueryBuilders\AbstractSqlBuilder;
use exface\Core\QueryBuilders\MsSqlBuilder;

/**
 * Exposes MS SQL date comparison internals while preserving its real CONVERT(date, ...) literal formatting.
 */
class DateOnlyComparisonMsSqlBuilder extends MsSqlBuilder
{
    /**
     * Exposes day-interval predicate generation so shared logic is verified with MS SQL's formatter.
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
}
