<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use League\Csv\Writer;
use exface\Core\CommonLogic\Constants\Icons;
use League\Csv\Reader;
use exface\Core\Interfaces\Widgets\iShowDataColumn;

/**
 *
 *
 *  ## Filename Placeholders
 *
 *
 *
 *  You can dynamically generate filenames based on aggregated data, by using placeholders in the property `filename`.
 *  For example `"filename":"[#=Now('yyyy-MM-dd')#]_[#~data:Materialkategorie:LIST_DISTINCT#]"` could be used to include both
 *  the current date and some information about the categories present in the export and result in a filename like `2024-09-10_Muffen`.
 *
 *  ### Supported placeholders:
 *
 *  - `[#=Formula()#]` Allows the use of formulas.
 *  - `[#~data:attribute_alias:AGGREGATOR#]` Aggregates the data column for the given alias by applying the specified aggregator. See below for
 * a list of supported aggregators.
 *
 *
 *
 *  ### Supported aggregators:
 *
 *  - `SUM` Sums up all values present in the column. Non-numeric values will either be read as numerics or as 0, if they cannot be converted.
 *  - `AVG` Calculates the arithmetic mean of all values present in the column. Non-numeric values will either be read as numerics or as 0, if they cannot be converted.
 *  - `MIN` Gets the lowest of all values present in the column. If only non-numeric values are present, their alphabetic rank is used. If the column is mixed,
 *  non-numeric values will be read as numerics or as 0, if they cannot be converted.
 *  - `MAX` Gets the highest of all values present in the column. If only non-numeric values are present, their alphabetic rank is used. If the column is mixed,
 *   non-numeric values will be read as numerics or as 0, if they cannot be converted.
 *  - `COUNT` Counts the total number of rows in the column.
 *  - `COUNT_DISTINCT` Counts the number of unique entries in the column, excluding empty rows.
 *  - `LIST` Lists all non-empty rows in the column, applying the following format: `Some value,anotherValue,yEt another VaLue` => `SomeValue_AnotherValue_YetAnotherValue`
 *  - `LIST_DISTINCT` Lists all unique, non-empty rows in the column, applying the following format: `Some value,anotherValue,yEt another VaLue` => `SomeValue_AnotherValue_YetAnotherValue`
 *
 *
 * @author SFL
 *
 */
class ExportCSV extends ExportJSON
{

    private $delimiterChar = ',';
    
    private $enclosureChar = '"';
    
    private $escapeChar = "\\";
    
    private $newlineSequence = "\n";
    
    private $bomSequence = '';
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::FILE_TEXT_O);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeHeader()
     */
    protected function writeHeader(array $exportedColumns) : array
    {
        $header = [];
        $output = [];
        $indexes = [];
        foreach ($exportedColumns as $widget) {
            if ($widget->isHidden()) {
                continue;   
            }
            
            // Name der Spalte
            if ($this->getUseAttributeAliasAsHeader() === true && ($widget instanceof iShowDataColumn) && $widget->isBoundToDataColumn()) {
                $colName = $widget->getAttributeAlias();
            } else {
                $colName = $widget->getCaption();
            }
            $colId = $widget->getDataColumnName();
            
            // Der Name muss einzigartig sein, sonst werden zu wenige Headerspalten
            // geschrieben.
            $idx = $indexes[$colId] ?? 0;
            $indexes[$colId] = $idx + 1;
            if ($idx > 1) {
                $colName = $idx;
            }
            
            $header[] = $colName;
            $output[] = $colId;
        }
        $this->getWriter()->insertOne($header);
        return $output;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeRows()
     */
    protected function writeRows(DataSheetInterface $dataSheet, array $headerKeys)
    {
        foreach ($dataSheet->getRows() as $row) {
            $outRow = [];
            foreach ($headerKeys as $key) {
                $outRow[$key] = $row[$key];
            }
            $this->getWriter()->insertOne($outRow);
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeFileResult()
     */
    protected function writeFileResult(DataSheetInterface $dataSheet)
    {}

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::getWriter()
     */
    protected function getWriter()
    {
        if (is_null($this->writer)) {
            $this->writer = Writer::createFromPath($this->getFilePathAbsolute(), 'x+');
            $this->writer->setDelimiter($this->delimiterChar);
            $this->writer->setEnclosure($this->enclosureChar);
            $this->writer->setEscape($this->escapeChar);
            $this->writer->setNewline($this->newlineSequence);
            $this->writer->setOutputBOM($this->bomSequence);
        }
        return $this->writer;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::getMimeType()
     */
    public function getMimeType() : ?string
    {
        return 'text/csv';
    }
    
    /**
     * Returns the delimiter used to separate different columns.
     * @return string
     */
    public function getDelimiter() {
        return $this->delimiterChar;
    }
    
    /**
     * Sets the delimiter used to separate different columns (default ",").
     *
     * @uxon-property delimiter
     * @uxon-type string
     *
     * @param string $value
     * @return \exface\Core\Actions\ExportCSV
     */
    public function setDelimiter($value) {
        $this->delimiterChar = $value;
        return $this;
    }
    
    /**
     * Returns the enclosure character used to enclose strings.
     * 
     * @return string
     */
    public function getEnclosure() {
        return $this->enclosureChar;
    }
    
    /**
     * Sets the enclosure character used to enclose strings (default '"').
     *
     * @uxon-property enclosure
     * @uxon-type string
     *
     * @param string $value
     * @return \exface\Core\Actions\ExportCSV
     */
    public function setEnclosure($value) {
        $this->enclosureChar = $value;
        return $this;
    }
    
    /**
     * Returns the character used to escape other characters.
     *
     * @return string
     */
    public function getEscape() {
        return $this->escapeChar;
    }
    
    /**
     * Sets the character used to escape other characters (default "\").
     *
     * @uxon-property escape
     * @uxon-type string
     *
     * @param string $value
     * @return \exface\Core\Actions\ExportCSV
     */
    public function setEscape($value) {
        $this->escapeChar = $value;
        return $this;
    }
    
    /**
     * Returns the sequence used for a line break.
     *
     * @return string
     */
    public function getNewline() {
        return $this->newlineSequence;
    }
    
    /**
     * Sets the sequence used for a line break (default "\n").
     *
     * @uxon-property newline
     * @uxon-type string
     *
     * @param string $value
     * @return \exface\Core\Actions\ExportCSV
     */
    public function setNewline($value) {
        $this->newlineSequence = $value;
        return $this;
    }
    
    /**
     * Returns the enclosure character used to enclose strings.
     *
     * @return string
     */
    public function getBom() {
        return $this->bomSequence;
    }
    
    /**
     * Sets the BOM (byte order mark) sequence to determine the byte order
     * (default "").
     * 
     * Possible values: "BOM-UTF8", "BOM-UTF16-BE", "BOM-UTF16-LE", "BOM-UTF32-BE",
     * "BOM-UTF32-LE"
     *
     * @uxon-property bom
     * @uxon-type string
     *
     * @param string $value
     * @return \exface\Core\Actions\ExportCSV
     */
    public function setBom($value) {
        switch ($value) {
            case "BOM-UTF8":
                $this->bomSequence = Reader::BOM_UTF8;
                break;
            case "BOM-UTF16-BE":
                $this->bomSequence = Reader::BOM_UTF16_BE;
                break;
            case "BOM-UTF16-LE":
                $this->bomSequence = Reader::BOM_UTF16_LE;
                break;
            case "BOM-UTF32-BE":
                $this->bomSequence = Reader::BOM_UTF32_BE;
                break;
            case "BOM-UTF32-LE":
                $this->bomSequence = Reader::BOM_UTF32_LE;
                break;
            default:
                $this->bomSequence = '';
        }
        return $this;
    }
}
?>