<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateTimeDataType;

/**
 * An input-field for dates with time.
 * 
 * The configuration is similar to the `InputDate` widget. Additionally you can se the following time-related
 * options:
 * 
 * - `show_seconds` - set to TRUE or FALSE to show/hide seconds
 * - `default_time` - specify a time to be set if the input is just a date
 * 
 * ## Supported input types
 *
 * - (+/-)? ... (t/d/w/m/j/y)? e.g.
 *     - `0` = today,
 *     - `-1` = yesterday
 *     - `1` or `+1` = tomorrow
 *     - `2w` = in two weeks,
 *     - `-5m` = 5 months ago
 *     - `+1y` = same date next year
 * - `today`, `now`, `yesterday`, `tomorrow`
 * - `dd.MM.yyyy`, `dd-MM-yyyy`, `dd/MM/yyyy`, `d.M.yyyy`, `d-M-yyyy`, `d/M/yyyy` (e.g. `30.09.2015` oder `30/9/2015`)
 * - `yyyy.MM.dd`, `yyyy-MM-dd`, `yyyy/MM/dd`, `yyyy.M.d`, `yyyy-M-d`, `yyyy/M/d` (e.g. `2015.09.30` oder `2015/9/30`)
 * - `dd.MM.yy`, `dd-MM-yy`, `dd/MM/yy`, `d.M.yy`, `d-M-yy`, `d/M/yy` (e.g. `30.09.15` or `30/9/15`)
 * - `yy.MM.dd`, `yy-MM-dd`, `yy/MM/dd`, `yy.M.d`, `yy-M-d`, `yy/M/d` (e.g. `32-09-30` for 30.09.2032)
 * - `dd.MM`, `dd-MM`, `dd/MM`, `d.M`, `d-M`, `d/M` (e.g. `30.09` oder `30/9`)
 * - `ddMMyyyy`, `ddMMyy`, `ddMM` (e.g. `30092015`, `300915` oder `3009`)
 * 
 *
 * ## Date formats
 *
 * The display `format` can be customized using the ICU syntax: http://userguide.icu-project.org/formatparse/datetime.
 * The default format depends on the locale (language) of the session - see `LOCALIZATION.DATE.DATE_TIME_FORMAT`
 * translation key.
 * 
 * @author Andrej Kabachnik
 *
 */
class InputDateTime extends InputDate
{
    private $showSeconds = null;
    private $defaultTime = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputDate::getFormatDefault()
     */
    protected function getFormatDefault() : string
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), DateTimeDataType::class)->getFormat();
    }
    
    /**
     *
     * @return bool
     */
    public function getShowSeconds() : bool
    {
        if ($this->showSeconds === null) {
            $dataType = $this->getValueDataType();
            if ($dataType instanceof DateTimeDataType) {
                return $dataType->getShowSeconds();
            } else {
                return false;
            }
        }
        return $this->showSeconds;
    }
    
    /**
     * Set to TRUE to show the seconds.
     *
     * @uxon-property show_seconds
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return InputDateTime
     */
    public function setShowSeconds(bool $value) : InputDateTime
    {
        $this->showSeconds = $value;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDefaultTime() : ?string
    {
        return $this->defaultTime;
    }

    /**
     * The default time (hh:mm:ss) to be assumed if the input is just a date.
     * 
     * If this property is not set, the current time is used instead.
     *
     * @uxon-property default_time
     * @uxon-type string
     * @uxon-default "00:00:00"
     *
     * @param string $time
     * @return InputDateTime
     */
    public function setDefaultTime(string $time) : InputDateTime
    {
        $this->defaultTime = $time;
        return $this;
    }
}