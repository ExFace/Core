<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\DataTypes\DateTimeDataType;

/**
 * This formatter generates javascript code to format and parse date/time via the library date.js.
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
 * - (+/-)? ... (t/d/w/m/j/y)? (z.B. 0 fuer heute, 1 oder 1d oder +1t fuer morgen, 2w fuer
 *      in 2 Wochen, -5m fuer vor 5 Monaten, +1j oder +1y fuer in 1 Jahr)
 * - today, heute, now, jetzt, yesterday, gestern, tomorrow, morgen
 * 
 * @method DateDataType getDataType()
 * 
 * @author Andrej Kabachnik
 *
 */
class JsDateFormatter extends AbstractJsDataTypeFormatter
{
    private $format = null;
    
    /**
     * 
     * {@inheritDoc}
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatter()
     */
    public function buildJsFormatter($jsInput)
    {
        return "moment(! {$jsInput} ? {$jsInput} : (isNaN({$jsInput}) ? exfTools.date.parse({$jsInput}) : new Date({$jsInput}))).formatPHP(\"{$this->getFormat()}\")";
    }
    
    /**
     * Returns inline javascript code to format the JS Date object behind the given variable name.
     * 
     * E.g. buildJsDateFormatter('Date()') would format the current date.
     * 
     * @param string $jsDateObject
     * @return string
     */
    public function buildJsDateFormatter($jsDateObject)
    {
        return "moment({$jsDateObject}).format(\"YYYY-MM-DD\")";
    }
    
    /**
     * Returns inline javascript code to turn the JS Date object behind the given variable 
     * name into a normalized date/time string.
     *
     * E.g. buildJsDateFormatter('Date()') would create a JS expression returning something like '2017-01-31'.
     *
     * @param string $jsDateObject
     * @return string
     */
    public function buildJsDateStringifier($jsDateObject)
    {
        return "moment({$jsDateObject}).format(\"{$this->buildJsDateFormatInternal()}\")";
    }
    
    /**
     * Returns a javascript function to parse string input into a JS Date object.
     * 
     * The function's name is set by buildJsDateParserFunctionName() and it accepts
     * a single argument - the string to parse.
     * 
     * function parseDate(date) {
     *  ...
     *  return Date(...);
     * }
     * 
     * @return string
     */
    protected function buildJsDateParserFunction()
    {
        // TODO: Muss angepasst werden um auch eingegebene Zeiten zu verarbeiten. Momentan
        // wird die Zeit ignoriert -> immer 00:00:00.
        // Vorsicht wenn neben dem Datum auch die Zeit uebergeben werden soll. In welcher
        // Zeitzone befindet sich der Client und der Server. In welcher Zeitzone erwartet
        // der Server die uebergebene Zeit? new Date(...) und date.toString arbeiten immer
        // mit der Zeitzone des Clients. Der Bootstrap Datepicker erwartet die
        // uebergebenen Dates in der UTC-Zeitzone und gibt auch entsprechende Dates
        // zurueck. Dates in UTC-Zeit koennen z.B. mit new Date(Date.UTC(yyyy, MM, dd))
        // erstellt werden.
        
        
        // IDEA: Der Code des Parsers wird noch hier erzeugt und steht nicht an einer
        // einzelnen Stelle wie z.B. facade.js, da er aus einer Konfiguration erzeugt
        // werden soll. Diese muesste die regulaeren Ausdruecke, sowie die Zuordnungen
        // der matches zu dd, MM, yyyy enthalten. Die Schwierigkeit besteht darin auch
        // Operationen wie 2000 + Number(match[3]) oder (new Date()).getFullYear()
        // abzubilden (vlt. durch Angabe von Jahrhundert, Jahr getrennt, bzw. currentYear
        // als Schluesselwort???). Diese Konfiguration koennte dann auch im DateDataType
        // verwendet werden um entsprechenden PHP-Code zu erzeugen um das Datum zu
        // parsen.
        
        // Auch moeglich: stattdessen Verwendung des DateJs-Parsers
        // date wird entsprechend CultureInfo geparst, hierfuer muss das entsprechende locale
        // DateJs eingebunden werden und ein kompatibler Formatter verwendet werden
        // return Date.parse(date);
        return <<<JS
        
        
    function {$this->buildJsDateParserFunctionName()}(date) {
        return exfTools.date.parse(date);
    }

JS;
    }
    
    /**
     * Returns the name of the date parser function: "parseDate" by default.
     * 
     * @return string
     */
    public function buildJsDateParserFunctionName()
    {
        return 'parseDate';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatParser()
     */
    public function buildJsFormatParser($jsInput)
    {
        return <<<JS

    function() {
        var dateObj = exfTools.date.parse({$jsInput});
        return (dateObj ? {$this->buildJsDateFormatter('dateObj')} : '');
    }()

JS;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildHtmlHeadIncludes()
     */
    public function buildHtmlHeadIncludes()
    {
        return [];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildHtmlBodyIncludes()
     */
    public function buildHtmlBodyIncludes()
    {
        return [
            '<script type="text/javascript" src="exface/moment/moment.min.js"></script>',
            '<script type="text/javascript" src="exface/vendor/exface/Core/Facades/AbstractAjaxFacade/js/exfTools.js"></script>',            
            '<script type="text/javascript">
                ' . $this->buildJsDateParserFunction() . '
            </script>'
        ];
    }
    
    /**
     * Generates the DateJs filename based on the locale provided by the translator.
     *
     * @return string
     */
    protected function buildDateJsLocaleFilename()
    {
        $dateJsBasepath = $this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . 'npm-asset' . DIRECTORY_SEPARATOR . 'datejs' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'production' . DIRECTORY_SEPARATOR;
        
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $locale = $translator->getLocale();
        $filename = 'date-' . str_replace("_", "-", $locale) . '.min.js';
        if (file_exists($dateJsBasepath . $filename)) {
            return $filename;
        }
        
        $fallbackLocales = $translator->getFallbackLocales();
        foreach ($fallbackLocales as $fallbackLocale) {
            $filename = 'date-' . str_replace("_", "-", $fallbackLocale) . '.min.js';
            if (file_exists($dateJsBasepath . $filename)) {
                return $filename;
            }
        }
        
        return 'date.min.js';
    }
    
    /**
     * Returns the format string for the interna date/time format (e.g. 2012-01-31 24:00:00) compatible
     * with the javscript library used for formatting.
     * 
     * @return string
     */
    protected function buildJsDateFormatInternal()
    {
        $type = $this->getDataType();
        return ($type instanceof TimestampDataType) || ($type instanceof DateTimeDataType) ? "YYYY-MM-DD HH:mm:ss" : "YYYY-MM-DD";
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
                return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT');
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
