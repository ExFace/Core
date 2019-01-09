<?php
namespace exface\Core\Widgets;

/**
 * An input-field for dates (without time).
 * 
 * Example:
 * 
 * ```
 *  {
 *      "object_alias": "alexa.RMS.CONSUMER_COMPLAINT",
 *      "attribute_alias": "COMPLAINT_DATE",
 *      "value": "today"
 *  }
 *  
 * ```
 * 
 * Supported input formats are:
 * 
 * - dd.MM.yyyy, dd-MM-yyyy, dd/MM/yyyy, d.M.yyyy, d-M-yyyy, d/M/yyyy (e.g. 30.09.2015 oder 30/9/2015)
 * - yyyy.MM.dd, yyyy-MM-dd, yyyy/MM/dd, yyyy.M.d, yyyy-M-d, yyyy/M/d (e.g. 2015.09.30 oder 2015/9/30)
 * - dd.MM.yy, dd-MM-yy, dd/MM/yy, d.M.yy, d-M-yy, d/M/yy (e.g. 30.09.15 oder 30/9/15)
 * - yy.MM.dd, yy-MM-dd, yy/MM/dd, yy.M.d, yy-M-d, yy/M/d (e.g. 32-09-30 fuer den 30.09.2032)
 * - dd.MM, dd-MM, dd/MM, d.M, d-M, d/M (e.g. 30.09 oder 30/9)
 * - ddMMyyyy, ddMMyy, ddMM (e.g. 30092015, 300915 oder 3009)
 * 
 * Shortcut-values: 
 * 
 * - (+/-)? ... (t/d/w/m/j/y)? (e.g. 0 = today, 1 or 1d or +1t = tomorrow, 2w = in two weeks, -5m = 5 months ago, +1y = 1 one year)
 * - today, heute, now, jetzt, yesterday, gestern, tomorrow, morgen
 * 
 * @author SFL
 *        
 */
class InputDate extends Input
{
    private $format = null;
    
    /**
     * @return string|null
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * Sets the display format for the value.
     * 
     * Supported input formats are:
     * 
     * - dd.MM.yyyy, dd-MM-yyyy, dd/MM/yyyy, d.M.yyyy, d-M-yyyy, d/M/yyyy (e.g. 30.09.2015 oder 30/9/2015)
     * - yyyy.MM.dd, yyyy-MM-dd, yyyy/MM/dd, yyyy.M.d, yyyy-M-d, yyyy/M/d (e.g. 2015.09.30 oder 2015/9/30)
     * - dd.MM.yy, dd-MM-yy, dd/MM/yy, d.M.yy, d-M-yy, d/M/yy (e.g. 30.09.15 oder 30/9/15)
     * - yy.MM.dd, yy-MM-dd, yy/MM/dd, yy.M.d, yy-M-d, yy/M/d (e.g. 32-09-30 fuer den 30.09.2032)
     * - dd.MM, dd-MM, dd/MM, d.M, d-M, d/M (e.g. 30.09 oder 30/9)
     * - ddMMyyyy, ddMMyy, ddMM (e.g. 30092015, 300915 oder 3009)
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