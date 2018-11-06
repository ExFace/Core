<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use League\Csv\Writer;
use exface\Core\CommonLogic\Constants\Icons;
use League\Csv\Reader;
use exface\Core\Interfaces\Widgets\iShowData;

/**
 * Exports data to a csv file.
 * 
 * The format of the CSV can be customized via the following properties:
 * 
 * - `delimiter_char` - `,` by default
 * - `enclosure_char` - `"` by default
 * - `escape_char` - `\` by default
 * - `newline_sequence` - `\n` by default
 * - `bom_sequence` - empty by default
 * 
 * As all export actions do, this action will read all data matching the current filters (no pagination), eventually
 * splitting it into multiple requests. You can use `limit_rows_per_request` and `limit_time_per_request` to control this.
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
    protected function writeHeader(iShowData $dataWidget) : array
    {
        $header = [];
        $output = [];
        $indexes = [];
        foreach ($dataWidget->getColumns() as $col) {
            if (! $col->isHidden()) {
                // Name der Spalte
                if ($this->getUseAttributeAliasAsHeader() === false) {
                    $colName = $col->getCaption();
                } else {
                    $colName = $col->getAttributeAlias();
                }
                $colId = $col->getDataColumnName();
                
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