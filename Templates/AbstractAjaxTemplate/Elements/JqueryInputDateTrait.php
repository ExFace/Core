<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\CommonLogic\Workbench;

/**
 *
 * @method Workbench getWorkbench()
 * 
 * @author SFL
 *        
 */
trait JqueryInputDateTrait {

    private $dateFormatScreen;

    private $dateFormatInternal;

    /**
     * Returns the format which is used to show dates on the screen.
     *
     * The format is specified in the translation files "DATE.FORMAT.SCREEN".
     *
     * @return unknown
     */
    protected function buildJsDateFormatScreen()
    {
        if (is_null($this->dateFormatScreen)) {
            $this->dateFormatScreen = $this->translate("DATE.FORMAT.SCREEN");
        }
        return $this->dateFormatScreen;
    }

    /**
     * Returns the format which is used for dates internally, eg to send them to the
     * server.
     *
     * The format is specified in the translation files "DATE.FORMAT.INTERNAL".
     *
     * @return unknown
     */
    protected function buildJsDateFormatInternal()
    {
        if (is_null($this->dateFormatInternal)) {
            $this->dateFormatInternal = $this->translate("DATE.FORMAT.INTERNAL");
        }
        return $this->dateFormatInternal;
    }

    /**
     * Generates the DateJs filename based on the locale provided by the translator.
     *
     * @return string
     */
    protected function buildDateJsLocaleFilename()
    {
        $dateJsBasepath = $this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . 'npm-asset' . DIRECTORY_SEPARATOR . 'datejs' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'production' . DIRECTORY_SEPARATOR;
        
        $locale = $this->getTemplate()->getApp()->getTranslator()->getLocale();
        $filename = 'date-' . str_replace("_", "-", $locale) . '.min.js';
        if (file_exists($dateJsBasepath . $filename)) {
            return $filename;
        }
        
        $fallbackLocales = $this->getTemplate()->getApp()->getTranslator()->getFallbackLocales();
        foreach ($fallbackLocales as $fallbackLocale) {
            $filename = 'date-' . str_replace("_", "-", $fallbackLocale) . '.min.js';
            if (file_exists($dateJsBasepath . $filename)) {
                return $filename;
            }
        }
        
        return 'date.min.js';
    }

    protected function buildJsDateParser()
    {
        // IDEA: Der Code des Parsers wird noch hier erzeugt und steht nicht an einer
        // einzelnen Stelle wie z.B. template.js, da er aus einer Konfiguration erzeugt
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
        $output = <<<JS

    function {$this->buildJsFunctionPrefix()}dateParser(date) {
        // date ist ein String und wird zu einem date-Objekt geparst
        
        // Variablen initialisieren
        var {$this->getId()}_jquery = $("#{$this->getId()}");
        var match = null;
        var dateParsed = false;
        var dateValid = false;
        
        // dd.MM.yyyy, dd-MM-yyyy, dd/MM/yyyy, d.M.yyyy, d-M-yyyy, d/M/yyyy
        if (!dateParsed && (match = /(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{4})/.exec(date)) != null) {
            var yyyy = Number(match[3]);
            var MM = Number(match[2]) - 1;
            var dd = Number(match[1]);
            dateParsed = true;
            dateValid = Date.validateYear(yyyy) && Date.validateMonth(MM) && Date.validateDay(dd, yyyy, MM);
        }
        // yyyy.MM.dd, yyyy-MM-dd, yyyy/MM/dd, yyyy.M.d, yyyy-M-d, yyyy/M/d
        if (!dateParsed && (match = /(\d{4})[.\-/](\d{1,2})[.\-/](\d{1,2})/.exec(date)) != null) {
            var yyyy = Number(match[1]);
            var MM = Number(match[2]) - 1;
            var dd = Number(match[3]);
            dateParsed = true;
            dateValid = Date.validateYear(yyyy) && Date.validateMonth(MM) && Date.validateDay(dd, yyyy, MM);
        }
        // dd.MM.yy, dd-MM-yy, dd/MM/yy, d.M.yy, d-M-yy, d/M/yy
        if (!dateParsed && (match = /(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{2})/.exec(date)) != null) {
            var yyyy = 2000 + Number(match[3]);
            var MM = Number(match[2]) - 1;
            var dd = Number(match[1]);
            dateParsed = true;
            dateValid = Date.validateYear(yyyy) && Date.validateMonth(MM) && Date.validateDay(dd, yyyy, MM);
        }
        // yy.MM.dd, yy-MM-dd, yy/MM/dd, yy.M.d, yy-M-d, yy/M/d
        if (!dateParsed && (match = /(\d{2})[.\-/](\d{1,2})[.\-/](\d{1,2})/.exec(date)) != null) {
            var yyyy = 2000 + Number(match[1]);
            var MM = Number(match[2]) - 1;
            var dd = Number(match[3]);
            dateParsed = true;
            dateValid = Date.validateYear(yyyy) && Date.validateMonth(MM) && Date.validateDay(dd, yyyy, MM);
        }
        // dd.MM, dd-MM, dd/MM, d.M, d-M, d/M
        if (!dateParsed && (match = /(\d{1,2})[.\-/](\d{1,2})/.exec(date)) != null) {
            var yyyy = (new Date()).getFullYear();
            var MM = Number(match[2]) - 1;
            var dd = Number(match[1]);
            dateParsed = true;
            dateValid = Date.validateYear(yyyy) && Date.validateMonth(MM) && Date.validateDay(dd, yyyy, MM);
        }
        // ddMMyyyy
        if (!dateParsed && (match = /^(\d{2})(\d{2})(\d{4})$/.exec(date)) != null) {
            var yyyy = Number(match[3]);
            var MM = Number(match[2]) - 1;
            var dd = Number(match[1]);
            dateParsed = true;
            dateValid = Date.validateYear(yyyy) && Date.validateMonth(MM) && Date.validateDay(dd, yyyy, MM);
        }
        // ddMMyy
        if (!dateParsed && (match = /^(\d{2})(\d{2})(\d{2})$/.exec(date)) != null) {
            var yyyy = 2000 + Number(match[3]);
            var MM = Number(match[2]) - 1;
            var dd = Number(match[1]);
            dateParsed = true;
            dateValid = Date.validateYear(yyyy) && Date.validateMonth(MM) && Date.validateDay(dd, yyyy, MM);
        }
        // ddMM
        if (!dateParsed && (match = /^(\d{2})(\d{2})$/.exec(date)) != null) {
            var yyyy = (new Date()).getFullYear();
            var MM = Number(match[2]) - 1;
            var dd = Number(match[1]);
            dateParsed = true;
            dateValid = Date.validateYear(yyyy) && Date.validateMonth(MM) && Date.validateDay(dd, yyyy, MM);
        }
        // Ausgabe des geparsten Wertes
        if (dateParsed && dateValid) {
            var output = new Date(yyyy, MM, dd);
            {$this->getId()}_jquery.data("_internalValue", output.toString("{$this->buildJsDateFormatInternal()}"));
            {$this->getId()}_jquery.data("_isValid", true);
            return output;
        }
        
        // (+/-)? ... (T/D/W/M/J/Y)?
        if (!dateParsed && (match = /^([+\-]?\d{1,3})([TtDdWwMmJjYy]?)$/.exec(date)) != null) {
            var output = Date.today();
            switch (match[2].toUpperCase()) {
                case "T":
                case "D":
                case "":
                    output.addDays(Number(match[1]));
                    break;
                case "W":
                    output.addWeeks(Number(match[1]));
                    break;
                case "M":
                    output.addMonths(Number(match[1]));
                    break;
                case "J":
                case "Y":
                    output.addYears(Number(match[1]));
            }
            dateParsed = true;
            dateValid = true;
        }
        // TODAY, HEUTE, NOW, JETZT, YESTERDAY, GESTERN, TOMORROW, MORGEN
        if (!dateParsed) {
            switch (date.toUpperCase()) {
                case "TODAY":
                case "HEUTE":
                case "NOW":
                case "JETZT":
                    var output = Date.today();
                    dateParsed = true;
                    dateValid = true;
                    break;
                case "YESTERDAY":
                case "GESTERN":
                    var output = Date.today().addDays(-1);
                    dateParsed = true;
                    dateValid = true;
                    break;
                case "TOMORROW":
                case "MORGEN":
                    var output = Date.today().addDays(1);
                    dateParsed = true;
                    dateValid = true;
            }
        }
        // Ausgabe des geparsten Wertes
        if (dateParsed && dateValid) {
            {$this->getId()}_jquery.data("_internalValue", output.toString("{$this->buildJsDateFormatInternal()}"));
            {$this->getId()}_jquery.data("_isValid", true);
            return output;
        } else {
            {$this->getId()}_jquery.data("_internalValue", "");
            {$this->getId()}_jquery.data("_isValid", false);
            return null;
        }
    }
JS;
        
        return $output;
    }
}
