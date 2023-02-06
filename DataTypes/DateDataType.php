<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Dates without time.
 * 
 * This data type will cause values from data sources to be parsed into a unified internal
 * date format, so they can be processed by templates, formulas, etc. without caring about
 * the native date formatting in the data source. It also makes sure, dates are formatted
 * according to the users locale and language whenever they are shown to the user.
 * 
 * By default, the short date notation of the current locale is used to display dates 
 * (e.g. `31.12.2021` for most european countries). However, a custom display format can be 
 * defined in the configuration of a particular data type model using its `format` property. 
 * The standardized ICU syntax is supported - see `format` property for details.
 * 
 * The format supported in date inputs depends on the widget and the facade used. In most
 * cases, the internal format (see below), the current display format and the following shorthand 
 * date formats are supported:
 * 
 * - +/- {n} days (e.g. `+1` for tomorrow, `-1` for yesterday, `0` for today)
 * - +/- {n}w weeks (e.g. `+1w` for next week)
 * - +/- {n}m months (e.g. `-3m` for three months ago)
 * - +/- {n}y years (e.g. `-1y` for the same date last year)
 * - dd.MM, dd-MM, dd/MM, d.M, d-M, d/M (e.g. `30.09` or `30/9`) for the current year
 * - ddMMyyyy, ddMMyy, ddMM (e.g. `30092015`, `300915` or `3009`)
 * 
 * Internally the workbench always operates with the `yyyy-MM-dd` format (e.g. `2021-12-31`).
 * The internal format cannot be changed!
 * 
 * @author Andrej Kabachnik
 *
 */
class DateDataType extends AbstractDataType
{
    const TIMESTAMP_MIN_VALUE = 100000;
    
    const DATE_FORMAT_INTERNAL = 'Y-m-d';
    
    const DATE_ICU_FORMAT_INTERNAL = 'yyyy-MM-dd';

    const PERIOD_YEAR = 'Y';
    
    const PERIOD_MONTH = 'M';
    
    const PERIOD_WEEK = 'W';
    
    const PERIOD_DAY = 'D';
    
    private $format = null;
    
    private $timeZone = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::cast()
     */
    public static function cast($string, bool $returnPhpDate = false, string $fromTimeZone = null)
    {
        $string = trim($string);
        if ($fromTimeZone !== null && $fromTimeZone === date_default_timezone_get()) {
            $fromTimeZone = null;
        }
        
        // Return NULL for casting empty values as an empty string '' actually is not a date!
        if (static::isValueEmpty($string) === true || static::isValueLogicalNull($string)) {
            return null;
        }
        
        $parsedString = null;
        switch (true) {
            // If a timestamp is passed (seconds since epoche), it must not be interpreted as
            // a relative date - therefore prefix really large numbers with an @, which will
            // mark it as a timestamp for the \DateTime consturctor.
            case is_numeric($string) && intval($string) >= self::TIMESTAMP_MIN_VALUE:
                $parsedString = '@' . $string;
                break;            
            case $relative = static::parseRelativeDate($string):
                $parsedString = $relative;
                break;
            case $short = static::parseShortDate($string):
                $parsedString = $short;
                break;
            // Numeric values, that are neither relative nor short dates, must be invalid!
            case is_numeric($string) && intval($string) < self::TIMESTAMP_MIN_VALUE:
                throw new DataTypeCastingError('Cannot convert "' . $string . '" to a date!', '6W25AB1');
                break;
        }        
        
        if ($parsedString !== null && $returnPhpDate === false && $fromTimeZone === null) {
            return $parsedString; 
        }
        
        try {
            $tz = $fromTimeZone !== null ? new \DateTimeZone($fromTimeZone) : null;
            $dateTime = new \DateTime($string, $tz);
        } catch (\Exception $e) {
            throw new DataTypeCastingError('Cannot convert "' . $string . '" to a date!', '6W25AB1', $e);
        }
        
        if ($tz !== null) {
            $dateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }
        
        return $returnPhpDate === true ? $dateTime : static::castFromPhpDate($dateTime, $returnPhpDate);
    }
    
    /**
     * 
     * @param string|\DateTimeInterface $dateTime
     * @param string $fromTimeZone
     * @param string $toTimeZone
     * @param bool $returnPhpDate
     * @return string|\DateTimeInterface|NULL
     */
    public static function convertTimeZone($dateTime, string $fromTimeZone, string $toTimeZone, bool $returnPhpDate = false)
    {
        if ($dateTime === null || $dateTime === '') {
            return null;
        }
        if (! $dateTime instanceof \DateTimeInterface) {
            $dateTime = static::cast($dateTime, true, $fromTimeZone);
        }
        $dateTime->setTimezone(new \DateTimeZone($toTimeZone));
        return $returnPhpDate === true ? $dateTime : static::castFromPhpDate($dateTime, $returnPhpDate);
    }
    
    /**
     * 
     * @param \DateTimeInterface $dateTime
     * @param bool $returnPhpDate
     * @return \DateTimeInterface|string
     */
    public static function castFromPhpDate(\DateTimeInterface $dateTime, bool $returnPhpDate = false)
    {
        $normalized = static::formatDateNormalized($dateTime);
        return $returnPhpDate ? new \DateTime($normalized) : $normalized;
    }
    
    /**
     * 
     * @param mixed $string
     * @return \DateTime|NULL
     */
    public static function castToPhpDate($string) : ?\DateTime
    {
        return static::cast($string, true);
    }
    
    /**
     * 
     * @param string|NULL $string
     * @param string $format
     * @param string $locale
     * @param bool $returnPhpDate
     * @throws DataTypeCastingError
     * @return string|\DateTimeInterface|NULL
     */
    public static function castFromFormat($string, string $format, string $locale, bool $returnPhpDate = false)
    {
        $intl = static::createIntlDateFormatter($locale, $format);
        $ts = $intl->parse($string);
        if ($ts === false) {
            throw new DataTypeCastingError('Cannot cast "' . $string . '" to date using format "' . $format . '" and locale "' . $locale . '"!');
        }
        $date = (new \DateTime)->setTimestamp($ts);
        return $returnPhpDate ? $date : static::formatDateNormalized($date);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::parse()
     */
    public function parse($value)
    {
        try {
            return parent::parse($value);
        } catch (DataTypeValidationError | DataTypeCastingError $e) {
            $parsed = $this->getIntlDateFormatter()->parse($value);
            if ($parsed === false) {
                throw $e;
            }
            return $parsed;
        }
    }
    
    /**
     * 
     * @param string $locale
     * @param string $format
     * @return \IntlDateFormatter
     */
    protected static function createIntlDateFormatter(string $locale, string $format, string $timezone = null) : \IntlDateFormatter
    {
        return new \IntlDateFormatter(
            $locale, 
            null, // date type
            null, // time type
            ($timezone === null ? null : new \DateTimeZone($timezone)), // time zone
            null, // calendar
            $format // pattern
        );
    }
    
    /**
     * 
     * @return \IntlDateFormatter
     */
    protected function getIntlDateFormatter() : \IntlDateFormatter
    {
        return self::createIntlDateFormatter($this->getLocale(), $this->getFormat(), $this->getFormatToTimeZone());
    }
    
    /**
     * 
     * @return string
     */
    public function getLocale() : string
    {
        return $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
    }
    
    /**
     * 
     * @param mixed $string
     * @return string|boolean
     */
    public static function parseShortDate($string)
    {
        $matches = [];
        if (strlen($string) == 4 && is_int($string)){
            return static::formatDateNormalized(new \DateTime($string . '-01-01'));
        } elseif (preg_match('/^([0-9]{1,2})[\.-]([0-9]{4})$/', $string, $matches)){
            return $matches[2] . '-' . $matches[1] . '-01';
        } elseif (preg_match('/^([0-9]{1,2})[\.-]([0-9]{1,2})[\.-]?$/', $string, $matches)){
            return date("Y") . '-' . $matches[2] . '-' . $matches[1];
        } else {
            return false;
        }
    }
    
    /**
     * 
     * @param mixed $string
     * @throws UnexpectedValueException
     * @return boolean|string
     */
    public static function parseRelativeDate($string)
    {
        $day_period = 'D';
        $week_period = 'W';
        $month_period = 'M';
        $year_period = 'Y';
        
        $matches = [];
        if (preg_match('/^([\+-]?[0-9]+)([dDmMwWyY]?)$/', $string, $matches)){
            $period = $matches[2];
            $quantifier = intval($matches[1]);
            // If the quatifier is zero, but the match is not, it must be some invalid numeric string
            // like '0000' - this is not a valid relative date!!!
            if ($quantifier === 0 && $matches[1] !== 0 && $matches[1] !== '0') {
                return false;
            }
            $interval_spec = 'P' . abs($quantifier);
            switch (strtoupper($period)){
                case $day_period:
                case '':
                    $interval_spec .= 'D';
                    break;
                case $week_period:
                    $interval_spec .= 'W';
                    break;
                case $month_period:
                    $interval_spec .= 'M';
                    break;
                case $year_period:
                    $interval_spec .= 'Y';
                    break;
                default:
                    throw new UnexpectedValueException('Invalid period "' . $period . '" used in relative date "' . $quantifier . $period . '"!', '6W25AB1');
            }
            $date = new \DateTime();
            $interval = new \DateInterval($interval_spec);
            if ($quantifier > 0){
                $date->add($interval);
            } else {
                $date->sub($interval);
            }
            return static::formatDateNormalized($date);
        }
        return false;
    }
    
    /**
     * Returns the given date in the internal normalized format: e.g. `2021-12-31 23:59:59`
     * 
     * @param \DateTimeInterface $date
     * @return string
     */
    public static function formatDateNormalized(\DateTimeInterface $date) : string
    {
        return $date->format(self::DATE_FORMAT_INTERNAL);
    }
    
    /**
     * Returns the give date formatted accoring to the current session locale and translation used.
     * 
     * For instantiated data types use `formatDate()` instead: it takes into account eventually
     * customized formatting for this particular data type model.
     * 
     * @see formatDate()
     * 
     * @param \DateTimeInterface $date
     * @param WorkbenchInterface $workbench
     * 
     * @return string
     */
    public static function formatDateLocalized(\DateTimeInterface $date, WorkbenchInterface $workbench) : string
    {
        $format = static::getFormatFromLocale($workbench);
        $locale = $workbench->getContext()->getScopeSession()->getSessionLocale();
        $formatter = static::createIntlDateFormatter($locale, $format);
        return $formatter->format($date);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string
     */
    public static function getFormatFromLocale(WorkbenchInterface $workbench) : string
    {
        return $workbench->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATE_FORMAT');
    }
    
    /**
     * 
     * @param \DateTimeInterface $date
     * @return string
     */
    public function formatDate(\DateTimeInterface $date) : string
    {
        return $this->getIntlDateFormatter()->format($date);
    }
    
    /**
     * 
     * @return string
     */
    public static function now() : string
    {
        return static::formatDateNormalized((new \DateTime()));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirectionsDataType::DESC($this->getWorkbench());
    }
    
    /**
     * 
     * @return string
     */
    public function getFormatToParseTo() : string
    {
        return self::DATE_ICU_FORMAT_INTERNAL;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasCustomFormat() : bool
    {
        return $this->format !== null;
    }
    
    /**
     * 
     * @return string
     */
    public function getFormat() : string
    {
        return $this->format ?? self::getFormatFromLocale($this->getWorkbench());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getInputFormatHint()
     */
    public function getInputFormatHint() : string
    {
        return $this->getApp()->getTranslator()->translate('LOCALIZATION.DATE.DATE_FORMAT_HINT');
    }
    
    /**
     * Display format for the date - see ICU formatting: http://userguide.icu-project.org/formatparse/datetime
     *
     * Typical formats are:
     *
     * - `dd.MM.yyyy` -> 31.12.2019
     * - `dd.MM.yy` -> 31.12.19
     * - `MMM yy` -> Dec 19
     * 
     * For most numerical fields, the number of characters specifies the field width. For example, if 
     * h is the hour, 'h' might produce '5', but 'hh' produces '05'. For some characters, the count 
     * specifies whether an abbreviated or full form should be used, but may have other choices, as 
     * given below.
     * 
     * Text within single quotes is not interpreted in any way (except for two adjacent single quotes). 
     * Otherwise all ASCII letter from a to z and A to Z are reserved as syntax characters, and require 
     * quoting if they are to represent literal characters. Two single quotes represents a literal 
     * single quote, either inside or outside single quotes.
     * 
     * Any characters in the pattern that are not in the ranges of [`a`..`z`] and [`A`..`Z`] will be treated as 
     * quoted text. For instance, characters like `:`, `.`, ` `, `#` and `@` will appear in the resulting time text 
     * even they are not enclosed within single quotes.The single quote is used to 'escape' letters. Two single quotes 
     * in a row, whether inside or outside a quoted sequence, represent a 'real' single quote.
     * 
     * ## Available placeholders
     * 
     *  | Symbol |    Meaning             |    Example            |    Result         |
     *  | ------ | ----------------- | ----------       | ---------         |
     *  |    G   |    era designator   |    G, GG, or GGG    |    AD               |
     *  |        |                     |    GGGG            |    Anno Domini       |
     *  |        |                     |    GGGGG            |    A    |
     *  |    y   |    year             |    yy                |    96    |
     *  |        |                     |    y or yyyy        |    1996    |
     *  |    Y   |    year of "Week of Year"    |    Y    |    1997    |
     *  |    u   |    extended year    |    u    |    4601    |
     *  |    U   |    cyclic year name, as in Chinese lunar calendar    |    U    |    甲子    |
     *  |    r   |    related Gregorian year    |    r    |    1996    |
     *  |    Q   |    quarter             |    Q    |    2    |
     *  |        |                     |    QQ    |    2    |
     *  |        |                     |    QQQ    |    Q2    |
     *  |        |                     |    QQQQ    |    2nd quarter    |
     *  |        |                     |    QQQQQ    |    2    |
     *  |    q   |    Stand Alone quarter    |    q    |    2    |
     *  |        |                     |    qq    |    2    |
     *  |        |                     |    qqq    |    Q2    |
     *  |        |                     |    qqqq    |    2nd quarter    |
     *  |        |                     |    qqqqq    |    2    |
     *  |    M   |    month in year     |    M    |    9    |
     *  |        |                     |    MM    |    9    |
     *  |        |                     |    MMM    |    Sep    |
     *  |        |                     |    MMMM    |    September    |
     *  |        |                     |    MMMMM    |    S    |
     *  |    L   |    Stand Alone month in year    |    L    |    9    |
     *  |        |                     |    LL    |    9    |
     *  |        |                     |    LLL    |    Sep    |
     *  |        |                     |    LLLL    |    September    |
     *  |        |                     |    LLLLL    |    S    |
     *  |    w   |    week of year     |    w    |    27    |
     *  |        |                     |    ww    |    27    |
     *  |    W   |    week of month     |    W    |    2    |
     *  |    d   |    day in month     |    d    |    2    |
     *  |        |                     |    dd    |    2    |
     *  |    D   |    day of year         |    D    |    189    |
     *  |    F   |    day of week in month    |    F    |    2 (2nd Wed in July)    |
     *  |    g   |    modified julian day    |    g    |    2451334    |
     *  |    E   |    day of week         |    E, EE, or EEE    |    Tue    |
     *  |        |                     |    EEEE    |    Tuesday    |
     *  |        |                     |    EEEEE    |    T    |
     *  |        |                     |    EEEEEE    |    Tu    |
     *  |    e   |    local day of wee |    e or ee    |    2    |
     *  |        |    example: if Monday is 1st day, Tuesday is 2nd )    |    eee    |    Tue    |
     *  |        |                     |    eeee    |    Tuesday    |
     *  |        |                     |    eeeee    |    T    |
     *  |        |                     |    eeeeee    |    Tu    |
     *  |    c   |    Stand Alone local day of week    |    c or cc    |    2    |
     *  |        |                     |    ccc    |    Tue    |
     *  |        |                     |    cccc    |    Tuesday    |
     *  |        |                     |    ccccc    |    T    |
     *  |        |                     |    cccccc    |    Tu    |
     *  |    a   |    am/pm marker     |    a    |    pm    |
     *  |    h   |    hour in am/pm (1~12)    |    h    |    7    |
     *  |        |        |    hh    |    7    |
     *  |    H   |    hour in day (0~23)    |    H    |    0    |
     *  |        |        |    HH    |    0    |
     *  |    k   |    hour in day (1~24)    |    k    |    24    |
     *  |        |        |    kk    |    24    |
     *  |    K   |    hour in am/pm (0~11)    |    K    |    0    |
     *  |        |        |    KK    |    0    |
     *  |    m   |    minute in hour    |    m    |    4    |
     *  |        |        |    mm    |    4    |
     *  |    s   |    second in minute    |    s    |    5    |
     *  |        |        |    ss    |    5    |
     *  |    S   |    fractional second - truncates (like other time fields)    |    S    |    2    |
     *  |        |    to the count of letters when formatting. Appends    |    SS    |    23    |
     *  |        |    zeros if more than 3 letters specified. Truncates at    |    SSS    |    235    |
     *  |        |    three significant digits when parsing.     |    SSSS    |    2350    |
     *  |    A   |    milliseconds in day    |    A    |    61201235    |
     *  |    z   |    Time Zone: specific non-location    |    z, zz, or zzz    |    PDT    |
     *  |        |        |    zzzz    |    Pacific Daylight Time    |
     *  |    Z   |    Time Zone: ISO8601 basic hms? / RFC 822    |    Z, ZZ, or ZZZ    |    -800    |
     *  |        |    Time Zone: long localized GMT (=OOOO)    |    ZZZZ    |    GMT-08:00    |
     *  |        |    TIme Zone: ISO8601 extended hms? (=XXXXX)    |    ZZZZZ    |    -08:00, -07:52:58, Z    |
     *  |    O   |    Time Zone: short localized GMT    |    O    |    GMT-8    |
     *  |        |    Time Zone: long localized GMT (=ZZZZ)    |    OOOO    |    GMT-08:00    |
     *  |    v   |    Time Zone: generic non-location    |    v    |    PT    |
     *  |        |    (falls back first to VVVV)    |    vvvv    |    Pacific Time or Los Angeles Time    |
     *  |    V   |    Time Zone: short time zone ID    |    V    |    uslax    |
     *  |        |    Time Zone: long time zone ID    |    VV    |    America/Los_Angeles    |
     *  |        |    Time Zone: time zone exemplar city    |    VVV    |    Los Angeles    |
     *  |        |    Time Zone: generic location (falls back to OOOO)    |    VVVV    |    Los Angeles Time    |
     *  |    X   |    Time Zone: ISO8601 basic hm?, with Z for 0    |    X    |    -08, +0530, Z    |
     *  |        |    Time Zone: ISO8601 basic hm, with Z    |    XX    |    -0800, Z    |
     *  |        |    Time Zone: ISO8601 extended hm, with Z    |    XXX    |    -08:00, Z    |
     *  |        |    Time Zone: ISO8601 basic hms?, with Z    |    XXXX    |    -0800, -075258, Z    |
     *  |        |    Time Zone: ISO8601 extended hms?, with Z    |    XXXXX    |    -08:00, -07:52:58, Z    |
     *  |    x   |    Time Zone: ISO8601 basic hm?, without Z for 0    |    x    |    522    |
     *  |        |    Time Zone: ISO8601 basic hm, without Z    |    xx    |    -800    |
     *  |        |    Time Zone: ISO8601 extended hm, without Z    |    xxx    |    -08:00    |
     *  |        |    Time Zone: ISO8601 basic hms?, without Z    |    xxxx    |    -76058    |
     *  |        |    Time Zone: ISO8601 extended hms?, without Z    |    xxxxx    |    -08:00, -07:52:58    |
     *  |    '   |    escape for text    |    '    |    (nothing)    |
     *  |    ''  |    two single quotes produce one    |    ' '    |    '    |
     *
     * @uxon-property format
     * @uxon-type string
     * @uxon-template dd.MM.yyyy
     *
     * @param string $format
     * @return DateDataType
     */
    public function setFormat(string $value) : DateDataType
    {
        $this->format = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::format()
     */
    public function format($value = null) : string
    {
        $date = $this::castToPhpDate($value ?? $this->getValue());
        
        if ($date === null) {
            return '';
        }
        
        return $this->formatDate($date);
    }
    
    /**
     * 
     * @param string $date1
     * @param string $date2
     * @return \DateInterval
     */
    public static function diff(string $date1, string $date2 = null) : \DateInterval
    {
        $dateTime1 = static::cast($date1, true);
        $dateTime2 = $date2 === null ? new \DateTimeImmutable() : static::cast($date2, true);
        return $dateTime1->diff($dateTime2);
    }
    
    /**
     * Adds a positive or negative interval to a date/time value.
     * 
     * Supported intervals:
     * 
     * - `Y` - years, 
     * - `M` - months
     * - `W` - weeks
     * - `D` - days (default)
     * - `h` - hours
     * - `m` - minutes 
     * - `s` - seconds
     * 
     * @param string|\DateTimeInterface $date
     * @param int $number
     * @param string $period
     * @param bool $returnPhpDate
     * @return string|\DateTimeInterface
     */
    public static function addInterval($dateOrString, int $number, string $period = self::PERIOD_DAY, bool $returnPhpDate = false)
    {
        if ($dateOrString === null || $dateOrString === '') {
            return $dateOrString;
        }
        if ($number === 0) {
            switch (true) {
                case $returnPhpDate && is_string($dateOrString):
                    return static::cast($dateOrString, true);
                case ! $returnPhpDate && $dateOrString instanceof \DateTimeInterface:
                    return static::formatDateNormalized($dateOrString);
                default: return $dateOrString;
            }
        }
        
        $dateTime = $dateOrString instanceof \DateTimeInterface ? $dateOrString : static::cast($dateOrString, true);
        $intervalNumber = abs($number);
        switch ($period) {
            case TimeDataType::PERIOD_HOUR:
            case 'H':
            case TimeDataType::PERIOD_MINUTE:
            case TimeDataType::PERIOD_SECOND:
            case 'S':
                $interval = 'PT' . $intervalNumber . strtoupper($period);
                break;
            case static::PERIOD_YEAR:
            case static::PERIOD_MONTH:
            case static::PERIOD_WEEK:
            case static::PERIOD_DAY:
                $interval = 'P' . $intervalNumber . $period;
                break;
            default:
                throw new UnexpectedValueException('Invalid date/time interval "' . $period . '" provided!');
        }
        $dateInterval = new \DateInterval($interval);
        $result = $number > 0 ? $dateTime->add($dateInterval) : $dateTime->sub($dateInterval);
        
        return $returnPhpDate ? $result : static::formatDateNormalized($result);
    }
    
    /**
     * 
     * @return string
     */
    public function getFormatToTimeZone() : ?string
    {
        return $this->timeZone;
    }
    
    /**
     * If set, the value will be displayed in the specified timezone
     * 
     * @uxon-property format_to_time_zone
     * @uxon-type string
     * 
     * @param string $value
     * @return DateDataType
     */
    public function setFormatToTimeZone(string $value) : DateDataType
    {
        $this->timeZone = $value;
        return $this;
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string
     */
    public static function getTimeZoneDefault(WorkbenchInterface $workbench) : string
    {
        return date_default_timezone_get();
    }
}