<?php
namespace exface\Core\Tests\PHPUnit\Unit\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilter;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Tests\PHPUnit\Support\QueryBuilders\DateOnlyComparisonMsSqlBuilder;
use exface\Core\Tests\PHPUnit\Support\QueryBuilders\DateOnlyComparisonMySqlBuilder;
use exface\Core\Tests\PHPUnit\Support\QueryBuilders\DateOnlyComparisonPostgreSqlBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Protects date-only SQL comparisons from excluding timestamp values later on the selected day.
 *
 * PostgreSQL covers the full buildSqlWhereComparator() integration path. MySQL and MS SQL cover the shared helper
 * with their real literal formatting; MySQL receives a constructor-free Workbench because its formatter consults
 * the Workbench only for the default time zone.
 */
class DateOnlySqlComparisonTest extends TestCase
{
    /**
     * Loads the framework's canonical constants without starting Workbench so the test uses production values.
     *
     * @return void
     */
    public static function setUpBeforeClass() : void
    {
        parent::setUpBeforeClass();
        if (! defined('EXF_LOGICAL_NULL')) {
            require_once __DIR__ . '/../../../../CommonLogic/Constants.php';
        }
    }

    /**
     * Comparators affected by timestamp time components use sargable day boundaries.
     *
     * @return void
     */
    public function testAffectedComparatorsUseDayIntervals() : void
    {
        $builder = $this->createPostgreSqlBuilder();
        $dataType = $this->createDataType(DateDataType::class);

        $this->assertSame(
            "created_on < '2026-09-03'",
            $builder->buildDateOnlyComparison('created_on', ComparatorDataType::LESS_THAN_OR_EQUALS, '2026-09-02', false, $dataType)
        );
        $this->assertSame(
            "created_on >= '2026-09-03'",
            $builder->buildDateOnlyComparison('created_on', ComparatorDataType::GREATER_THAN, '2026-09-02', false, $dataType)
        );
        $this->assertSame(
            "(created_on >= '2026-09-02' AND created_on < '2026-09-03')",
            $builder->buildDateOnlyComparison('created_on', ComparatorDataType::EQUALS, '2026-09-02', false, $dataType)
        );
        $this->assertSame(
            "(created_on < '2026-09-02' OR created_on >= '2026-09-03')",
            $builder->buildDateOnlyComparison('created_on', ComparatorDataType::EQUALS_NOT, '2026-09-02', false, $dataType)
        );
    }

    /**
     * Comparators outside the helper's strict day-interval scope must continue to its caller unchanged.
     *
     * @return void
     */
    public function testUnaffectedComparatorsAreNotRewritten() : void
    {
        $builder = $this->createPostgreSqlBuilder();
        $dataType = $this->createDataType(DateDataType::class);

        foreach ([
            ComparatorDataType::GREATER_THAN_OR_EQUALS,
            ComparatorDataType::LESS_THAN,
            ComparatorDataType::IN,
            ComparatorDataType::NOT_IN,
            ComparatorDataType::IS,
            ComparatorDataType::IS_NOT,
        ] as $comparator) {
            $this->assertNull(
                $builder->buildDateOnlyComparison('created_on', $comparator, '2026-09-02', false, $dataType),
                'Comparator ' . $comparator . ' must bypass the day-interval helper.'
            );
        }
    }

    /**
     * Non-literal, date-time and empty values bypass day-interval rewriting to preserve existing handling.
     *
     * @return void
     */
    public function testNonDateOnlyLiteralValuesAreNotRewritten() : void
    {
        $builder = $this->createPostgreSqlBuilder();
        $dateType = $this->createDataType(DateDataType::class);
        $dateTimeType = $this->createDataType(DateTimeDataType::class);

        $this->assertNull(
            $builder->buildDateOnlyComparison('created_on', ComparatorDataType::EQUALS, 'other_date', true, $dateType)
        );
        $this->assertNull(
            $builder->buildDateOnlyComparison('created_on', ComparatorDataType::EQUALS, '2026-09-02 12:00:00', false, $dateTimeType)
        );
        $this->assertNull(
            $builder->buildDateOnlyComparison('created_on', ComparatorDataType::EQUALS, null, false, $dateType)
        );
        $this->assertNull(
            $builder->buildDateOnlyComparison('created_on', ComparatorDataType::IS, 'sql:like:2026-09-%', false, $dateType)
        );
    }

    /**
     * The public comparator builder must retain the helper's interval semantics and all unaffected predicate behavior.
     *
     * @return void
     */
    public function testBuildSqlWhereComparatorIntegration() : void
    {
        $builder = $this->createPostgreSqlBuilder();
        $dateType = $this->createDataType(DateDataType::class);
        $dateTimeType = $this->createDataType(DateTimeDataType::class);
        $datePart = $this->createQueryPart($dateType);

        $expectedDateComparisons = [
            ComparatorDataType::LESS_THAN_OR_EQUALS => "created_on < '2026-09-03'",
            ComparatorDataType::GREATER_THAN => "created_on >= '2026-09-03'",
            ComparatorDataType::EQUALS => "(created_on >= '2026-09-02' AND created_on < '2026-09-03')",
            ComparatorDataType::EQUALS_NOT => "(created_on < '2026-09-02' OR created_on >= '2026-09-03')",
            ComparatorDataType::GREATER_THAN_OR_EQUALS => "created_on >= '2026-09-02'",
            ComparatorDataType::LESS_THAN => "created_on < '2026-09-02'",
        ];
        foreach ($expectedDateComparisons as $comparator => $expectedSql) {
            $this->assertSame(
                $expectedSql,
                $builder->buildWhereComparison($datePart, 'created_on', $comparator, '2026-09-02')
            );
        }

        $this->assertSame(
            "created_on <= '2026-09-02 12:00:00'",
            $builder->buildWhereComparison(
                $this->createQueryPart($dateTimeType),
                'created_on',
                ComparatorDataType::LESS_THAN_OR_EQUALS,
                '2026-09-02 12:00:00'
            )
        );
        $this->assertSame(
            'created_on IS NULL',
            $builder->buildWhereComparison($datePart, 'created_on', ComparatorDataType::EQUALS, null)
        );
        $this->assertSame(
            'created_on IS NOT NULL',
            $builder->buildWhereComparison($datePart, 'created_on', ComparatorDataType::EQUALS_NOT, null)
        );
        $this->assertSame(
            "/* created_on cannot pass comparison to \"abc\" via comparator \"<=\": wrong data type! */\n1 = 0",
            $builder->buildWhereComparison($datePart, 'created_on', ComparatorDataType::LESS_THAN_OR_EQUALS, 'abc')
        );
    }

    /**
     * Date IS optimization must feed strict equality into the day-interval comparison path.
     *
     * @return void
     */
    public function testDateIsComparatorIsOptimizedToDayInterval() : void
    {
        $builder = $this->createPostgreSqlBuilder();
        $dataType = $this->createDataType(DateDataType::class);
        $attribute = $this->createStub(MetaAttributeInterface::class);
        $attribute->method('isRelation')->willReturn(false);
        $mainObject = $this->createStub(MetaObjectInterface::class);
        $mainObject->method('hasUidAttribute')->willReturn(false);
        $this->setBuilderMainObject($builder, $mainObject);

        $queryPart = $this->createQueryPart($dataType);
        $queryPart->method('getCompareValue')->willReturn('2026-09-02');
        $queryPart->method('getComparator')->willReturn(ComparatorDataType::IS);
        $queryPart->method('getAttribute')->willReturn($attribute);

        $this->assertSame(
            "(created_on >= '2026-09-02' AND created_on < '2026-09-03')",
            $builder->buildOptimizedWhereComparison($queryPart, 'created_on')
        );
    }

    /**
     * Calendar transitions must calculate the next boundary in PHP without invalid intermediate dates.
     *
     * @return void
     */
    public function testDateBoundaryTransitions() : void
    {
        $builder = $this->createPostgreSqlBuilder();
        $dataType = $this->createDataType(DateDataType::class);
        $boundaries = [
            '2026-12-31' => '2027-01-01',
            '2028-02-28' => '2028-02-29',
            '2028-02-29' => '2028-03-01',
            '2026-04-30' => '2026-05-01',
        ];

        foreach ($boundaries as $date => $nextDate) {
            $this->assertSame(
                "created_on < '{$nextDate}'",
                $builder->buildDateOnlyComparison(
                    'created_on',
                    ComparatorDataType::LESS_THAN_OR_EQUALS,
                    $date,
                    false,
                    $dataType
                )
            );
        }
    }

    /**
     * Logical NULL bypasses helper rewriting while malformed dates surface a casting error for the caller to handle.
     *
     * @return void
     */
    public function testHelperNullAndInvalidValueBehavior() : void
    {
        $builder = $this->createPostgreSqlBuilder();
        $dataType = $this->createDataType(DateDataType::class);

        $this->assertNull(
            $builder->buildDateOnlyComparison(
                'created_on',
                ComparatorDataType::EQUALS,
                EXF_LOGICAL_NULL,
                false,
                $dataType
            )
        );

        foreach (['abc', '2026-13-45'] as $invalidDate) {
            try {
                $builder->buildDateOnlyComparison(
                    'created_on',
                    ComparatorDataType::EQUALS,
                    $invalidDate,
                    false,
                    $dataType
                );
                $this->fail('Invalid date "' . $invalidDate . '" must throw DataTypeCastingError.');
            } catch (DataTypeCastingError $e) {
                $this->assertSame($invalidDate, $e->getValue());
            }
        }
    }

    /**
     * Concrete builders must preserve their native literal syntax around the shared day-interval predicates.
     *
     * @return void
     */
    public function testOtherDialectLiteralFormats() : void
    {
        $dataType = $this->createDataType(DateDataType::class);
        $cases = [
            [
                $this->createMySqlBuilder(),
                [
                    ComparatorDataType::LESS_THAN_OR_EQUALS => "created_on < {ts '2026-09-03'}",
                    ComparatorDataType::GREATER_THAN => "created_on >= {ts '2026-09-03'}",
                    ComparatorDataType::EQUALS => "(created_on >= {ts '2026-09-02'} AND created_on < {ts '2026-09-03'})",
                    ComparatorDataType::EQUALS_NOT => "(created_on < {ts '2026-09-02'} OR created_on >= {ts '2026-09-03'})",
                ],
            ],
            [
                $this->createMsSqlBuilder(),
                [
                    ComparatorDataType::LESS_THAN_OR_EQUALS => "created_on < CONVERT(date, '2026-09-03', 23)",
                    ComparatorDataType::GREATER_THAN => "created_on >= CONVERT(date, '2026-09-03', 23)",
                    ComparatorDataType::EQUALS => "(created_on >= CONVERT(date, '2026-09-02', 23) AND created_on < CONVERT(date, '2026-09-03', 23))",
                    ComparatorDataType::EQUALS_NOT => "(created_on < CONVERT(date, '2026-09-02', 23) OR created_on >= CONVERT(date, '2026-09-03', 23))",
                ],
            ],
        ];

        foreach ($cases as [$builder, $expectedComparisons]) {
            foreach ($expectedComparisons as $comparator => $expectedSql) {
                $this->assertSame(
                    $expectedSql,
                    $builder->buildDateOnlyComparison('created_on', $comparator, '2026-09-02', false, $dataType)
                );
            }
        }
    }

    /**
     * Creates a constructor-free PostgreSQL builder with only the logger dependency needed by invalid-value handling.
     *
     * @return DateOnlyComparisonPostgreSqlBuilder
     */
    private function createPostgreSqlBuilder() : DateOnlyComparisonPostgreSqlBuilder
    {
        return $this->createBuilder(DateOnlyComparisonPostgreSqlBuilder::class);
    }

    /**
     * Creates a constructor-free MySQL builder while retaining its real date and time-zone formatting path.
     *
     * @return DateOnlyComparisonMySqlBuilder
     */
    private function createMySqlBuilder() : DateOnlyComparisonMySqlBuilder
    {
        return $this->createBuilder(DateOnlyComparisonMySqlBuilder::class);
    }

    /**
     * Creates a constructor-free MS SQL builder while retaining its real CONVERT(date, ...) formatting.
     *
     * @return DateOnlyComparisonMsSqlBuilder
     */
    private function createMsSqlBuilder() : DateOnlyComparisonMsSqlBuilder
    {
        return $this->createBuilder(DateOnlyComparisonMsSqlBuilder::class);
    }

    /**
     * Creates a SQL builder without booting Workbench and injects only a real Workbench carrying a logger stub.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function createBuilder(string $class) : object
    {
        $builder = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $workbench = (new ReflectionClass(Workbench::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Workbench::class, 'logger'))->setValue(
            $workbench,
            $this->createStub(LoggerInterface::class)
        );
        (new ReflectionProperty(AbstractQueryBuilder::class, 'workbench'))->setValue($builder, $workbench);
        return $builder;
    }

    /**
     * Creates a constructor-free data type because static date normalization does not require Workbench state.
     *
     * @param class-string<DataTypeInterface> $class
     * @return DataTypeInterface
     */
    private function createDataType(string $class) : DataTypeInterface
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    /**
     * Mocks only the query-part accessors used by buildSqlWhereComparator() so no metamodel is required.
     *
     * @param DataTypeInterface $dataType
     * @param bool $valueIsSql
     * @return QueryPartFilter
     */
    private function createQueryPart(DataTypeInterface $dataType, bool $valueIsSql = false) : QueryPartFilter
    {
        $queryPart = $this->createStub(QueryPartFilter::class);
        $queryPart->method('getDataType')->willReturn($dataType);
        $queryPart->method('getDataAddressProperties')->willReturn([]);
        $queryPart->method('getValueListDelimiter')->willReturn(',');
        $queryPart->method('isValueDataAddress')->willReturn($valueIsSql);
        return $queryPart;
    }

    /**
     * Sets the constructor-free builder's main object so comparator optimization can inspect UID metadata safely.
     *
     * @param DateOnlyComparisonPostgreSqlBuilder $builder
     * @param MetaObjectInterface $mainObject
     * @return void
     */
    private function setBuilderMainObject(
        DateOnlyComparisonPostgreSqlBuilder $builder,
        MetaObjectInterface $mainObject
    ) : void {
        (new ReflectionProperty(AbstractQueryBuilder::class, 'main_object'))->setValue($builder, $mainObject);
    }
}
