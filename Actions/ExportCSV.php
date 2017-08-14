<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use League\Csv\Writer;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Widgets\DataTable;
use League\Csv\Reader;

/**
 * Exports data to a csv file.
 *
 * @author SFL
 *
 */
class ExportCSV extends ExportDataFile
{

    private $delimiterChar = ',';
    
    private $enclosureChar = '"';
    
    private $escapeChar = "\\";
    
    private $newlineSequence = "\n";
    
    private $bomSequence = '';
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportData::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIconName(Icons::FILE_TEXT_O);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeHeader()
     */
    protected function writeHeader(DataSheetInterface $dataSheet)
    {
        /** @var DataTable $inputWidget */
        $inputWidget = $this->getCalledByWidget()->getInputWidget();
        $header = [];
        $output = [];
        foreach ($dataSheet->getColumns() as $col) {
            if (! $col->getHidden()) {
                // Name der Spalte
                if ($this->getWriteReadableHeader()) {
                    if (($dataTableCol = $inputWidget->getColumnByAttributeAlias($col->getAttributeAlias())) || ($dataTableCol = $inputWidget->getColumnByDataColumnName($col->getName()))) {
                        $colName = $dataTableCol->getCaption();
                    } elseif ($colAttribute = $col->getAttribute()) {
                        $colName = $colAttribute->getName();
                    } else {
                        $colName = '';
                    }
                } else {
                    $colName = $col->getName();
                }
                // Der Name muss einzigartig sein, sonst werden zu wenige Headerspalten
                // geschrieben.
                while (array_key_exists($colName, $header)) {
                    $colName = $colName . ' ';
                }
                
                $header[] = $colName;
                $output[] = $col->getName();
            }
        }
        $this->getWriter()->insertOne($header);
        return $output;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeRows()
     */
    protected function writeRows(DataSheetInterface $dataSheet, array $headerKeys)
    {
        foreach ($dataSheet->getRows() as $row) {
            $rowKeys = array_keys($row);
            $outRow = [];
            foreach ($headerKeys as $key) {
                if (! (array_search($key, $rowKeys) === false)) {
                    $outRow[] = $row[$key];
                } else {
                    $outRow[] = null;
                }
            }
            $this->getWriter()->insertOne($outRow);
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeFileResult()
     */
    protected function writeFileResult(DataSheetInterface $dataSheet)
    {}

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::getWriter()
     */
    protected function getWriter()
    {
        if (is_null($this->writer)) {
            $this->writer = Writer::createFromPath($this->getPathname(), 'x+');
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
     * @see \exface\Core\Actions\ExportData::getMimeType()
     */
    public function getMimeType()
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