<?php
namespace exface\Core\Widgets;

/**
 * Generates an input-field for dates.
 * 
 * Example:
 *  {
 *      "object_alias": "alexa.RMS.CONSUMER_COMPLAINT",
 *      "attribute_alias": "COMPLAINT_DATE",
 *      "id": "complaint_date",
 *      "value": "heute"
 *  }
 * 
 * Supported input-formats and -values are:
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
 * @author SFL
 *        
 */
class InputDate extends Input
{
}
?>