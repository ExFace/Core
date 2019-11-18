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
        return "exfTools.date.format((! {$jsInput} ? {$jsInput} : (isNaN({$jsInput}) ? exfTools.date.parse({$jsInput}) : new Date({$jsInput}))), \"{$this->getFormat()}\")";
    }
    
    /**
     * Returns inline javascript code to format the JS Date object behind the given variable name
     * into the internal used value.
     * 
     * E.g. buildJsFormatDateObjectToInternal('Date()') would format the current date.
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
     *
     * @param string $jsDateObject
     * @return string
     */
    public function buildJsFormatDateObjectToString($jsDateObject)
    {
        return "exfTools.date.format({$jsDateObject}, \"{$this->getFormat()}\")";
    }
    
    /**
     * Returns inline javascript code to turn the given String to a Date Object
     *
     *
     * @param string $jsString
     * @return string
     */
    public function buildJsParseStringToDateObject($jsString)
    {
        return "exfTools.date.parse({$jsString})";
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
        return (dateObj ? {$this->buildJsFormatDateObjectToString('dateObj')} : '');
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
            '<script type="text/javascript" src="exface/vendor/exface/Core/Facades/AbstractAjaxFacade/js/exfTools.js"></script>'           
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
        return $type->getFormatToParseTo();
        //return ($type instanceof TimestampDataType) || ($type instanceof DateTimeDataType) ? "YYYY-MM-DD HH:mm:ss" : "YYYY-MM-DD";
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
