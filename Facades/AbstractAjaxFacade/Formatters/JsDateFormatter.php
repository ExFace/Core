<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\DataTypes\DateTimeDataType;

/**
 * This formatter generates javascript code to format and parse date/time via the library moment.js.
 *
 * In addition to regular input formats, the following relative values are supported by
 * the parser:
 *
 * - dd.MM.yyyy, dd-MM-yyyy, dd/MM/yyyy, d.M.yyyy, d-M-yyyy, d/M/yyyy (z.B. 30.09.2015 oder 30/9/2015)
 * - yyyy.MM.dd, yyyy-MM-dd, yyyy/MM/dd, yyyy.M.d, yyyy-M-d, yyyy/M/d (z.B. 2015.09.30 oder 2015/9/30)
 * - dd.MM.yy, dd-MM-yy, dd/MM/yy, d.M.yy, d-M-yy, d/M/yy (z.B. 30.09.15 oder 30/9/15)
 * - yy.MM.dd, yy-MM-dd, yy/MM/dd, yy.M.d, yy-M-d, yy/M/d (z.B. 32-09-30 fuer den 30.09.2032)
 * - dd.MM, dd-MM, dd/MM, d.M, d-M, d/M (z.B. 30.09 oder 30/9)
 * - ddMMyyyy, ddMMyy, ddMM (z.B. 30092015, 300915 oder 3009)
 * - (+/-)? ... (t/d/w/m/j/y)? (z.B. 0 fuer heute, 1 oder 1d oder d fuer morgen, 2w fuer
 * in 2 Wochen, -5m fuer vor 5 Monaten, +1y fuer in 1 Jahr)
 * - today, now, yesterday, tomorrow
 * 
 * NOTE: this formatter requires the javascript libraries exfTools (part of AbstractAjaxFacade) and 
 * moment.js to be available via `exfTools` and `moment()` respectively! Add moment.js to the
 * `composer.json` of the facade like this:
 * 
 * ```
 * require: {
 *      ...
 * 		"npm-asset/moment" : "^2.24.0"
 *      ...
 * }
 * ```
 * 
 * NOTE: This formatter requires the exfTools JS library to be available!
 *
 * @method DateDataType getDataType()
 *        
 * @author Andrej Kabachnik
 *        
 */
class JsDateFormatter extends AbstractJsDataTypeFormatter
{
    const DATE_COMPARE_YEAR = 'year';
    
    const DATE_COMPARE_MONTH = 'month';
    
    const DATE_COMPARE_DAY = 'day';
    
    const DATE_COMPARE_HOUR = 'hour';
    
    const DATE_COMPARE_MINUTE = 'minute';
    
    const DATE_COMPARE_SECOND = 'second';
    
    const DATE_COMPARE_MILLISECOND = 'millisecond';
    
    private $format = null;

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Formatters\AbstractJsDataTypeFormatter::setDataType()
     */
    protected function setDataType(DataTypeInterface $dataType)
    {
        if (! $dataType instanceof DateDataType) {
            // TODO
        }
        return parent::setDataType($dataType);
    }

    /**
     * Formats an anything as a human-readable date.
     *
     * Accepts as input:
     * - empty values,
     * - numbers (seconds)
     * - parsable value (JS Date, ISO string, human-readable string)
     *
     * e.g. "now" -> 31.12.2019, "-2w" -> 17.12.2019
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatter()
     */
    public function buildJsFormatter($jsInput)
    {
        $formatQuoted = json_encode($this->getFormat());
        return "exfTools.date.format((! {$jsInput} ? {$jsInput} : (isNaN({$jsInput}) ? exfTools.date.parse({$jsInput}, {$formatQuoted}) : new Date({$jsInput}))), {$formatQuoted})";
    }

    /**
     * Returns inline javascript code to format the JS Date object behind the given variable name
     * into the internal used value.
     *
     * E.g. `buildJsFormatDateObjectToInternal('new Date()')` would format the current date to a format like 2019-12-31
     *
     * @param string $jsDateObject
     * @return string
     */
    public function buildJsFormatDateObjectToInternal($jsDateObject)
    {
        return "exfTools.date.format({$jsDateObject}, \"{$this->buildJsDateFormatInternal()}\")";
    }

    /**
     * Returns inline javascript code to turn the JS Date object behind the given variable
     * name into a formated string.
     *
     * e.g. buildJsFormatDateObjectToString('new Date()') -> format like 31.12.2019
     *
     *
     * @param string $jsDateObject
     * @return string
     */
    public function buildJsFormatDateObjectToString($jsDateObject)
    {
        $formatQuoted = json_encode($this->getFormat());
        return "exfTools.date.format({$jsDateObject}, {$formatQuoted})";
    }
    
    public function buildJsFormatDateObject(string $jsDateObject, string $ICUFormat) : string
    {
        $formatJs = json_encode($ICUFormat);
        return "exfTools.date.format({$jsDateObject}, $formatJs)";
    }

    /**
     * Returns inline javascript code to turn the given String to a Date Object.
     *
     * e.g. 31.12.2019 -> Date Object
     *
     * @param string $jsString
     * @return string
     */
    public function buildJsFormatParserToJsDate($jsString)
    {
        $formatQuoted = json_encode($this->getFormat());
        return "exfTools.date.parse({$jsString}, {$formatQuoted})";
    }

    /**
     * Returns inline JS code to parse a date string to the internal string format: e.g. 31.12.2019 -> 2019-12-31.
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatParser()
     */
    public function buildJsFormatParser($jsInput)
    {
        $formatQuoted = json_encode($this->getFormat());
        return <<<JS
function() {
                var dateObj = exfTools.date.parse({$jsInput}, {$formatQuoted});
                return (dateObj ? {$this->buildJsFormatDateObjectToInternal('dateObj')} : '');
            }()
        
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsValidator()
     */
    public function buildJsValidator(string $jsValue) : string
    {
        $formatQuoted = json_encode($this->getFormat());
        //TODO granularities als Konstanten
        $granularity = self::DATE_COMPARE_DAY;
        $dataType = $this->getDataType();
        if ($dataType instanceof DateTimeDataType) {
            if ($dataType->getShowMilliseconds()) {
                $granularity = self::DATE_COMPARE_MILLISECOND;
            } elseif ($dataType->getShowSeconds()) {
                $granularity = self::DATE_COMPARE_SECOND;
            }
        }
        $jsCompareMax = '';
        $jsCompareMin = '';
        if ($dataType->getMax() !== null) {
            $jsCompareMax = <<<JS
                sDateMax = '{$dataType->getMax()}';
                valid = exfTools.date.compareDates(mVal, sDateMax, '<=', sGranularity);
                if (valid !== true) {
                    return false;
                }
JS;
        }
        if ($dataType->getMin() !== null) {
            $jsCompareMin = <<<JS
                sDateMin = '{$dataType->getMin()}';
                valid = exfTools.date.compareDates(mVal, sDateMin, '>=', sGranularity);
JS;
        }
        return <<<JS
function() {
                var mVal = {$jsValue};
                var sGranularity = '{$granularity}';
                var sDateMax, sDateMin;
                var valid = mVal === null || mVal === '' || mVal === undefined || exfTools.date.parse(mVal, {$formatQuoted}) !== null;
                if (valid !== true) {
                    return false;
                }
                {$jsCompareMax}
                {$jsCompareMin}
                return valid;
            }()
            
JS;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildHtmlHeadIncludes()
     */
    public function buildHtmlHeadIncludes(FacadeInterface $facade) : array
    {
        return [];
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildHtmlBodyIncludes()
     */
    public function buildHtmlBodyIncludes(FacadeInterface $facade) : array
    {
        return [];
    }
    
    /**
     * Generates the moment locale include script based on the session locale
     *
     * @return string[]
     */
    public static function buildHtmlHeadMomentIncludes(FacadeInterface $facade) : array
    {
        $includes = [
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.MOMENT.JS') . '"></script>',
        ];
        $localesPath = $facade->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $facade->getConfig()->getOption('LIBS.MOMENT.LOCALES');
        $localesUrl = $facade->buildUrlToSource('LIBS.MOMENT.LOCALES');
        $fullLocale = $facade->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
        $locale = str_replace("_", "-", $fullLocale);
        $url = $localesUrl. DIRECTORY_SEPARATOR . $locale . '.js';
        if (file_exists($localesPath. DIRECTORY_SEPARATOR . $locale . '.js')) {
            $url = $localesUrl. DIRECTORY_SEPARATOR . $locale . '.js';
            $includes[] = "<script type='text/javascript' src='{$url}' charset='UTF-8'></script>";
        }
        $locale = substr($fullLocale, 0, strpos($fullLocale, '_'));
        $url = $localesUrl. DIRECTORY_SEPARATOR . $locale . '.js';
        if (file_exists($localesPath. DIRECTORY_SEPARATOR . $locale . '.js')) {
            $url = $localesUrl. DIRECTORY_SEPARATOR . $locale . '.js';
            $includes[] = "<script type='text/javascript' src='{$url}' charset='UTF-8'></script>";
        }
        
        return $includes;
    }


    /**
     * Returns the format string for the internal date/time format (e.g.
     * 2012-01-31 24:00:00) compatible
     * with the javscript library used for formatting.
     *
     * @return string
     */
    protected function buildJsDateFormatInternal()
    {
        $type = $this->getDataType();
        return $type->getFormatToParseTo();
    }

    /**
     * Returns the format string to be used in this formatter.
     *
     * If the format is not set excplicitly (e.g. by the widget), it will be determined
     * automatically from the data type.
     *
     * @return string
     */
    public function getFormat()
    {
        if (is_null($this->format)) {
            $type = $this->getDataType();
            if ($type instanceof DateDataType) {
                return $type->getFormat();
            } else {
                return $this->getWorkbench()
                    ->getCoreApp()
                    ->getTranslator()
                    ->translate('LOCALIZATION.DATE.DATETIME_FORMAT');
            }
        }
        return $this->format;
    }

    /**
     * Sets a specific format to be used by this formatter: the passed value must be compatible
     * with the javascript library used for formatting.
     *
     * @param string $formatString
     * @return \exface\Core\Facades\AbstractAjaxFacade\Formatters\JsDateFormatter
     */
    public function setFormat($formatString)
    {
        $this->format = $formatString;
        return $this;
    }
}