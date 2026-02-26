<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Widgets\Traits\SingleValueInputTrait;

/**
 * An input-field for dates (without time).
 * 
 * Example:
 * 
 * ```
 *  {
 *      "object_alias": "alexa.RMS.CONSUMER_COMPLAINT",
 *      "attribute_alias": "COMPLAINT_DATE",
 *      "value": "0"
 *  }
 *  
 * ```
 * 
 * ## Supported input types
 * 
 * - (+/-)? ... (t/d/w/m/j/y)? e.g. 
 *      - `0` = today, 
 *      - `-1` = yesterday
 *      - `1` or `+1` = tomorrow
 *      - `2w` = in two weeks, 
 *      - `-5m` = 5 months ago
 *      - `+1y` = same date next year
 * - `today`, `now`, `yesterday`, `tomorrow`
 * - `dd.MM.yyyy`, `dd-MM-yyyy`, `dd/MM/yyyy`, `d.M.yyyy`, `d-M-yyyy`, `d/M/yyyy` (e.g. `30.09.2015` oder `30/9/2015`)
 * - `yyyy.MM.dd`, `yyyy-MM-dd`, `yyyy/MM/dd`, `yyyy.M.d`, `yyyy-M-d`, `yyyy/M/d` (e.g. `2015.09.30` oder `2015/9/30`)
 * - `dd.MM.yy`, `dd-MM-yy`, `dd/MM/yy`, `d.M.yy`, `d-M-yy`, `d/M/yy` (e.g. `30.09.15` or `30/9/15`)
 * - `yy.MM.dd`, `yy-MM-dd`, `yy/MM/dd`, `yy.M.d`, `yy-M-d`, `yy/M/d` (e.g. `32-09-30` for 30.09.2032)
 * - `dd.MM`, `dd-MM`, `dd/MM`, `d.M`, `d-M`, `d/M` (e.g. `30.09` oder `30/9`)
 * - `ddMMyyyy`, `ddMMyy`, `ddMM` (e.g. `30092015`, `300915` oder `3009`) 
 * 
 * ## Date formats
 * 
 * The date `format` can be customized using the ICU syntax: http://userguide.icu-project.org/formatparse/datetime.
 * The default format depends on the locale (language) of the session - see `LOCALIZATION.DATE.DATE_FORMAT`
 * translation key.
 * 
 * @author SFL
 * @author Andrej Kabachnik
 *        
 */
class InputDate extends Input
{
    use SingleValueInputTrait;
    
    /**
     * Add an interval to the value: e.g. `add(+1d)`, `add(-1w)`
     *
     * @uxon-property add
     *
     * @var string
     */
    const FUNCTION_ADD = 'add';
    
    private $format = null;
    
    /**
     * @return string
     */
    public function getFormat() : string
    {
        if ($this->format === null) {
            $dataType = $this->getValueDataType();
            if ($dataType instanceof DateDataType || $dataType instanceof TimestampDataType) {
                $this->format = $dataType->getFormat();
            } else {
                $this->format = $this->getFormatDefault();
            }
        }
        return $this->format;
    }
    
    /**
     * Returns the default format of the Date data type.
     * 
     * @return string
     */
    protected function getFormatDefault() : string
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), DateDataType::class)->getFormat();
    }

    /**
     * Customize the display format for the date.
     * 
     * The format definition adheres to the ICU standard: http://userguide.icu-project.org/formatparse/datetime. If
     * no `format` is specified explicitly, the format defined in the session locale (current language) will be used.
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
     * ## Available format placeholders
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
     * 
     * @param string $format
     * @return InputDate
     */
    public function setFormat($format)
    {
        $this->format = $format;
        return $this;
    }
}
?>