<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * Exports data to a json file.
 *
 * @author SFL
 *
 */
class ExportJson extends ExportDataFile
{

    private $firstRowWritten = false;

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
        $header = [];
        foreach ($dataSheet->getColumns() as $col) {
            if (! $col->getHidden()) {
                $header[$col->getName()] = $col->getName();
            }
        }
        return $header;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeRows()
     */
    protected function writeRows(DataSheetInterface $dataSheet, array $columnNames)
    {
        foreach ($dataSheet->getRows() as $row) {
            $rowKeys = array_keys($row);
            $outRow = new \stdClass();
            foreach ($columnNames as $key => $name) {
                if (! (array_search($key, $rowKeys) === false)) {
                    $outRow->$name = $row[$key];
                } else {
                    $outRow->$name = null;
                }
            }
            if ($this->firstRowWritten) {
                fwrite($this->getWriter(), ',' . json_encode($outRow, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_QUOT));
            } else {
                fwrite($this->getWriter(), json_encode($outRow, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_QUOT));
                $this->firstRowWritten = true;
            }
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeFileResult()
     */
    protected function writeFileResult(DataSheetInterface $dataSheet)
    {
        fwrite($this->getWriter(), ']');
        fclose($this->getWriter());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::getWriter()
     */
    protected function getWriter()
    {
        if (is_null($this->writer)) {
            $this->writer = fopen($this->getPathname(), 'x+');
            fwrite($this->writer, '[');
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
        return 'application/json';
    }
}
?>