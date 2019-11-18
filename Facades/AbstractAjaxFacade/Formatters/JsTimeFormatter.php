<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\TimeDataType;

/**
 * This formatter generates javascript code to format and parse time via Date.js library.
 * 
 * @method TimeDataType getDataType()
 * 
 * @author Andrej Kabachnik
 *
 */
class JsTimeFormatter extends AbstractJsDataTypeFormatter
{
    private $format = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Formatters\AbstractJsDataTypeFormatter::setDataType()
     */
    protected function setDataType(DataTypeInterface $dataType)
    {
        if (! $dataType instanceof TimeDataType) {
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
        //return "(! {$jsInput} ? {$jsInput} : Date.parse({$jsInput}).toString(\"{$this->getFormat()}\"))";
        return "exfTools.time.format((! {$jsInput} ? {$jsInput} : ? exfTools.time.parse({$jsInput})), \"{$this->getFormat()}\")";
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
        return "exfTools.time.format({$jsDateObject}, \"{$this->buildJsDateFormatInternal()}\")";
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
        return "exfTools.time.format({$jsDateObject}, \"{$this->getFormat()}\")";
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
        return "exfTools.time.parse({$jsString})";
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
        var dateObj = exfTools.time.parse({$jsInput});
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
            if ($type instanceof TimeDataType) {
                $this->format = $type->getFormat();
                if ($type->getShowSeconds() === true) {
                    $this->format .= ':ss';
                }
                if ($type->getAmPm() === true) {
                    $this->format .= ' a';
                }
            } else {
                return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.TIME_FORMAT');
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