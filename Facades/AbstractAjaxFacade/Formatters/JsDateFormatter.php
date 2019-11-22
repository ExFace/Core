<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Interfaces\Facades\FacadeInterface;

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
 *      in 2 Wochen, -5m fuer vor 5 Monaten, +1y fuer in 1 Jahr)
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
 * If the authomatic header-include logic of the `AbstractAjaxFacade` is to be used (methods 
 * `buildHtmlBodyIncludes()` and `buildHtmlHeadIncludes()`), the following configuration options need
 * to be added to the facade:
 * 
 * ```
 *  "LIBS.MOMENT.JS": "npm-asset/moment/min/moment.min.js",
 *  "LIBS.EXFTOOLS.JS": "exface/Core/Facades/AbstractAjaxFacade/js/exfTools.js",
 * ```
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
     * E.g. buildJsFormatDateObjectToInternal('new Date()') would format the current date.
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
    public function buildJsFormatParserToJsDate($jsString)
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
    public function buildHtmlHeadIncludes(FacadeInterface $facade) : array
    {
        return [];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildHtmlBodyIncludes()
     */
    public function buildHtmlBodyIncludes(FacadeInterface $facade) : array
    {
        return [
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.MOMENT.JS') . '"></script>',
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.EXFTOOLS.JS') . '"></script>'           
        ];
    }
    
    /**
     * Generates the DateJs filename based on the locale provided by the translator.
     *
     * @return string
     */
    protected function buildMomentJsLocaleFilename()
    {
        $dateJsBasepath = $this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . 'npm-asset' . DIRECTORY_SEPARATOR . 'moment' . DIRECTORY_SEPARATOR . 'min' . DIRECTORY_SEPARATOR;
        
        /*$translator = $this->getWorkbench()->getCoreApp()->getTranslator();
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
        }*/
        
        return 'moment.min.js';
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
