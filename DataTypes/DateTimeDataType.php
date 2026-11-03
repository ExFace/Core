<?php
namespace exface\Core\DataTypes;

use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * A date with time
 * 
 * ## Time zone handling
 * 
 * The system assumes
 * 
 * @author andrej.kabachnik
 *
 */
class DateTimeDataType extends DateDataType
{   
    const DATETIME_FORMAT_INTERNAL = 'Y-m-d H:i:s';
    
    const DATETIME_ICU_FORMAT_INTERNAL = 'yyyy-MM-dd HH:mm:ss';
    
    private $showSeconds = false;
    
    private $showMilliseconds = false;
    
    private $timeZoneDependent = true;
    
    /**
     * 
     * @param \DateTime $date
     * @return string
     */
    public static function formatDateNormalized(\DateTimeInterface $date) : string
    {
        return $date->format(self::DATETIME_FORMAT_INTERNAL);
    }
    
    /**
     * 
     * @param \DateTimeInterface $dateTime
     * @param bool $returnPhpDate
     * @return \DateTimeInterface|string
     */
    public static function castFromPhpDate(\DateTimeInterface $dateTime, bool $returnPhpDate = false)
    {
        return $returnPhpDate ? $dateTime : static::formatDateNormalized($dateTime);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\DateDataType::getFormatToParseTo()
     */
    public function getFormatToParseTo() : string
    {
        return self::DATETIME_ICU_FORMAT_INTERNAL;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\DateDataType::getFormat()
     */
    public function getFormat() : string
    {
        return $this->hasCustomFormat() ? parent::getFormat() : static::getFormatFromLocale($this->getWorkbench(), $this->getShowSeconds());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\DateDataType::getInputFormatHint()
     */
    public function getInputFormatHint() : string
    {
        return $this->getApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT_HINT');
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string
     */
    public static function getFormatFromLocale(WorkbenchInterface $workbench, bool $withSeconds = false) : string
    {
        return $workbench->getCoreApp()->getTranslator()->translate($withSeconds ? 'LOCALIZATION.DATE.DATETIME_FORMAT_WITH_SECONDS' : 'LOCALIZATION.DATE.DATETIME_FORMAT');
    }
    
    /**
     *
     * @return bool
     */
    public function getShowSeconds() : bool
    {
        return $this->showSeconds;
    }
    
    /**
     * Set to TRUE to show the seconds (has no effect when custom `format` specified!).
     *
     * @uxon-property show_seconds
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return TimeDataType
     */
    public function setShowSeconds(bool $value) : DateTimeDataType
    {
        $this->showSeconds = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getShowMilliseconds() : bool
    {
        return $this->showMilliseconds;
    }
    
    /**
     * Set to TRUE to show use time with milliseconds (has no effect when custom `format` specified!).
     *
     * @uxon-property show_milliseconds
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return TimeDataType
     */
    public function setShowMilliseconds(bool $value) : DateTimeDataType
    {
        $this->showMilliseconds = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isTimeZoneDependent() : bool
    {
        return $this->timeZoneDependent;
    }
    
    /**
     * Set to FALSE to make values of this type ignore time zones completely
     *
     * @uxon-property time_zone_dependent
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $value
     * @return TimeDataType
     */
    public function setTimeZoneDependent(bool $value) : TimeDataType
    {
        $this->timeZoneDependent = $value;
        return $this;
    }

    /**
     * Returns the time part of a date-time string unchanged, or NULL if the string has no time.
     * 
     * - `2026-11-03 11:58:00` => `11:58:00`
     * - `2026-11-03 11:58` => `11:58`
     * 
     * @param string $value
     * @return string|null
     */
    public static function findTime(string $value) : ?string
    {
        // TODO use cast() here first!
        if (preg_match('/(?:^|[\sT])((?:[01]?[0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9](?:\.[0-9]+)?)?)(?![0-9:])/', $value, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
    
    /**
     * Splits a whole-day comparison into timestamp boundaries without changing the original condition.
     *
     * Equality uses `>=` midnight AND `<` next midnight; inequality uses `<` midnight OR `>=` next midnight.
     * `<=` becomes `<` next midnight, and `>` becomes `>=` next midnight. Explicit times, empty values and other
     * comparators return NULL. Boundaries are calendar midnights in the parsed date's time zone, not fixed
     * 24-hour offsets, so fractional seconds and daylight-saving transitions are preserved.
     *
     * @param ConditionInterface $condition
     * @return ConditionGroupInterface|null
     * @throws \exface\Core\Exceptions\DataTypes\DataTypeCastingError
     */
    public static function splitDayCondition(ConditionInterface $condition) : ?ConditionGroupInterface
    {
        $value = $condition->getValue();
        $comparator = $condition->getComparator();
        if (! is_string($value)
            || static::isValueEmpty($value)
            || static::isValueLogicalNull($value)
            || ! in_array($comparator, [
                ComparatorDataType::EQUALS,
                ComparatorDataType::EQUALS_NOT,
                ComparatorDataType::LESS_THAN_OR_EQUALS,
                ComparatorDataType::GREATER_THAN,
            ], true)
            || static::findTime($value) !== null
        ) {
            return null;
        }

        $date = \DateTimeImmutable::createFromInterface(static::cast($value, true))->setTime(0, 0, 0);
        $start = static::castFromPhpDate($date);
        $next = static::castFromPhpDate($date->modify('+1 day'));
        $group = ConditionGroupFactory::createEmpty(
            $condition->getWorkbench(),
            $comparator === ComparatorDataType::EQUALS_NOT ? EXF_LOGICAL_OR : EXF_LOGICAL_AND
        );
        $expression = $condition->getExpression();
        switch ($comparator) {
            case ComparatorDataType::EQUALS:
                $group->addConditionFromExpression($expression, $start, ComparatorDataType::GREATER_THAN_OR_EQUALS);
                $group->addConditionFromExpression($expression, $next, ComparatorDataType::LESS_THAN);
                break;
            case ComparatorDataType::EQUALS_NOT:
                $group->addConditionFromExpression($expression, $start, ComparatorDataType::LESS_THAN);
                $group->addConditionFromExpression($expression, $next, ComparatorDataType::GREATER_THAN_OR_EQUALS);
                break;
            case ComparatorDataType::LESS_THAN_OR_EQUALS:
                $group->addConditionFromExpression($expression, $next, ComparatorDataType::LESS_THAN);
                break;
            case ComparatorDataType::GREATER_THAN:
                $group->addConditionFromExpression($expression, $next, ComparatorDataType::GREATER_THAN_OR_EQUALS);
                break;
        }
        return $group;
    }
}