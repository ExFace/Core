<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\TimeDataType;

/**
 * This formatter generates javascript code to format and parse time via moment.js library.
 * 
 * @method TimeDataType getDataType()
 * 
 * @author Andrej Kabachnik
 *
 */
class JsTimeFormatter extends JsDateFormatter
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
     * Formats an anything as a human-readable time.
     *
     * Accepts as input:
     * - empty values,
     * - parsable value (ISO string, human-readable string)
     *
     * e.g. "+2h" -> 14:00, "-10m" -> 11:50
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatter()
     */
    public function buildJsFormatter($jsInput)
    {
        return "exfTools.time.format((! {$jsInput} ? {$jsInput} : exfTools.time.parse({$jsInput})), \"{$this->getFormat()}\")";
    }
    
     /**
     * Returns inline javascript code to format the JS Date object behind the given variable name
     * into the internal used value.
     * 
     * e.g. buildJsFormatDateObjectToInternal('new Date()') would format the current date to a format like '00:00:00'
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
     * e.g. buildJsFormatDateObjectToString('new Date()') -> format like '00:00'
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
     * e.g. 02:00 -> new Date('1970-01-01 02:00')
     *
     * @param string $jsString
     * @return string
     */
    public function buildJsFormatParserToJsDate($jsString)
    {
        return "function(){var sTime = exfTools.time.parse({$jsString}); return sTime ? new Date('1970-01-01 ' + sTime) : null}()";
    }
        
    /**
     * Returns inline JS code to parse a time string to the internal string format: e.g.
     * "+2h" -> 14:00:00.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatParser()
     */
    public function buildJsFormatParser($jsInput)
    {
        return "(exfTools.time.parse({$jsInput}) || '')";
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