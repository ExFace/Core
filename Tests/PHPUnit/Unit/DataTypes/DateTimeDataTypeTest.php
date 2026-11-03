<?php
namespace exface\Core\Tests\PHPUnit\Unit\DataTypes;

use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Model\ConditionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Covers time extraction and backend-independent whole-day condition splitting without a persisted metamodel.
 */
class DateTimeDataTypeTest extends TestCase
{
    /**
     * Extracting a time preserves its precision, including ISO separators and fractional seconds.
     *
     * @return void
     */
    public function testFindTimePreservesPrecision() : void
    {
        foreach ([
            '2026-11-03 11:58:00' => '11:58:00',
            '2026-11-03 11:58' => '11:58',
            '2026-11-03 00:00:00' => '00:00:00',
            '2026-11-03 23:59:59.999999' => '23:59:59.999999',
            '2026-11-03T11:58:00Z' => '11:58:00',
            '2026-11-03T11:58:00+02:00' => '11:58:00',
            '11:58:00' => '11:58:00',
            '2026-11-03 9:05' => '9:05',
        ] as $value => $expected) {
            $this->assertSame($expected, DateTimeDataType::findTime($value));
        }
    }

    /**
     * Values without an explicit time do not acquire a default midnight.
     *
     * @return void
     */
    public function testFindTimeReturnsNullWithoutTime() : void
    {
        foreach (['', '2026-11-03', '03.11.2026'] as $value) {
            $this->assertNull(DateTimeDataType::findTime($value));
        }
    }

    /**
     * Supported comparators produce half-open boundaries without mutating the original condition or expression.
     *
     * @return void
     */
    public function testSplitDayConditionBuildsHalfOpenGroups() : void
    {
        $workbench = new Workbench();
        $cases = [
            ComparatorDataType::EQUALS => [EXF_LOGICAL_AND, [
                [ComparatorDataType::GREATER_THAN_OR_EQUALS, '2026-11-03 00:00:00'],
                [ComparatorDataType::LESS_THAN, '2026-11-04 00:00:00'],
            ]],
            ComparatorDataType::EQUALS_NOT => [EXF_LOGICAL_OR, [
                [ComparatorDataType::LESS_THAN, '2026-11-03 00:00:00'],
                [ComparatorDataType::GREATER_THAN_OR_EQUALS, '2026-11-04 00:00:00'],
            ]],
            ComparatorDataType::LESS_THAN_OR_EQUALS => [EXF_LOGICAL_AND, [
                [ComparatorDataType::LESS_THAN, '2026-11-04 00:00:00'],
            ]],
            ComparatorDataType::GREATER_THAN => [EXF_LOGICAL_AND, [
                [ComparatorDataType::GREATER_THAN_OR_EQUALS, '2026-11-04 00:00:00'],
            ]],
        ];
        foreach ($cases as $comparator => [$operator, $expected]) {
            $condition = $this->createCondition($workbench, '2026-11-03', $comparator);
            $expression = $condition->getExpression();
            $group = DateTimeDataType::splitDayCondition($condition);

            $this->assertNotNull($group);
            $this->assertSame($operator, $group->getOperator());
            $this->assertSame([], $group->getNestedGroups());
            $actual = [];
            foreach ($group->getConditions() as $boundary) {
                $this->assertSame($expression, $boundary->getExpression());
                $actual[] = [$boundary->getComparator(), $boundary->getValue()];
                $this->assertNull(DateTimeDataType::splitDayCondition($boundary));
            }
            $this->assertSame($expected, $actual);
            $this->assertSame('2026-11-03', $condition->getValue());
            $this->assertSame($comparator, $condition->getComparator());
            $this->assertSame($expression, $condition->getExpression());
        }
    }

    /**
     * Empty values and explicit times, including midnight and fractional seconds, bypass day expansion.
     *
     * @return void
     */
    public function testSplitDayConditionSkipsEmptyValuesAndExplicitTimes() : void
    {
        $workbench = new Workbench();
        foreach ([
            null,
            '',
            EXF_LOGICAL_NULL,
            '2026-11-03 00:00:00',
            '2026-11-03 11:58',
            '2026-11-03T12:00:00Z',
            '2026-11-03 23:59:59.999999',
        ] as $value) {
            $condition = $this->createCondition($workbench, $value, ComparatorDataType::EQUALS);
            $this->assertNull(DateTimeDataType::splitDayCondition($condition));
        }
    }

    /**
     * List, explicit range, fuzzy and already-correct boundary comparators are not rewritten.
     *
     * @return void
     */
    public function testSplitDayConditionSkipsUnsupportedComparators() : void
    {
        $workbench = new Workbench();
        foreach ([
            ComparatorDataType::IN,
            ComparatorDataType::NOT_IN,
            ComparatorDataType::BETWEEN,
            ComparatorDataType::IS,
            ComparatorDataType::IS_NOT,
            ComparatorDataType::LESS_THAN,
            ComparatorDataType::GREATER_THAN_OR_EQUALS,
        ] as $comparator) {
            $condition = $this->createCondition($workbench, '2026-11-03', $comparator);
            $this->assertNull(DateTimeDataType::splitDayCondition($condition));
        }
    }

    /**
     * The next calendar midnight crosses month, year and leap-day boundaries correctly.
     *
     * @return void
     */
    public function testSplitDayConditionCalendarTransitions() : void
    {
        $workbench = new Workbench();
        foreach ([
            '2026-12-31' => '2027-01-01',
            '2028-02-28' => '2028-02-29',
            '2028-02-29' => '2028-03-01',
            '2026-04-30' => '2026-05-01',
        ] as $date => $nextDate) {
            $condition = $this->createCondition($workbench, $date, ComparatorDataType::LESS_THAN_OR_EQUALS);
            $group = DateTimeDataType::splitDayCondition($condition);
            $this->assertNotNull($group);
            $conditions = $group->getConditions();
            $this->assertCount(1, $conditions);
            $this->assertSame(ComparatorDataType::LESS_THAN, $conditions[0]->getComparator());
            $this->assertSame($nextDate . ' 00:00:00', $conditions[0]->getValue());
        }
    }

    /**
     * Day boundaries remain local midnights on both short and long daylight-saving days.
     *
     * @return void
     */
    public function testSplitDayConditionDaylightSavingTransitions() : void
    {
        $workbench = new Workbench();
        $originalTimeZone = date_default_timezone_get();
        try {
            date_default_timezone_set('Europe/Berlin');
            foreach (['2026-03-29' => 23, '2026-10-25' => 25] as $date => $hours) {
                $condition = $this->createCondition($workbench, $date, ComparatorDataType::EQUALS);
                $group = DateTimeDataType::splitDayCondition($condition);
                $this->assertNotNull($group);
                $conditions = $group->getConditions();
                $this->assertCount(2, $conditions);
                $start = new \DateTimeImmutable($conditions[0]->getValue());
                $end = new \DateTimeImmutable($conditions[1]->getValue());
                $this->assertSame('00:00:00', $start->format('H:i:s'));
                $this->assertSame('00:00:00', $end->format('H:i:s'));
                $this->assertSame($hours * 3600, $end->getTimestamp() - $start->getTimestamp());
            }
        } finally {
            date_default_timezone_set($originalTimeZone);
        }
    }

    /**
     * Malformed dates surface a casting error rather than an empty or successful-looking range.
     *
     * @return void
     */
    public function testSplitDayConditionRejectsMalformedDate() : void
    {
        $condition = $this->createCondition(new Workbench(), 'abc', ComparatorDataType::EQUALS);
        $this->expectException(DataTypeCastingError::class);
        DateTimeDataType::splitDayCondition($condition);
    }

    /**
     * Invalid month and day values surface the date parser's casting error.
     *
     * @return void
     */
    public function testSplitDayConditionRejectsInvalidCalendarDate() : void
    {
        $condition = $this->createCondition(new Workbench(), '2026-13-45', ComparatorDataType::EQUALS);
        $this->expectException(DataTypeCastingError::class);
        DateTimeDataType::splitDayCondition($condition);
    }

    /**
     * Creates a real condition with a scalar expression without loading any metamodel or starting Workbench.
     *
     * @param Workbench $workbench
     * @param string|null $value
     * @param string $comparator
     * @return ConditionInterface
     */
    private function createCondition(Workbench $workbench, ?string $value, string $comparator) : ConditionInterface
    {
        return ConditionFactory::createFromExpression(
            $workbench,
            ExpressionFactory::createAsScalar($workbench, 'mtime'),
            $value,
            $comparator
        );
    }
}
