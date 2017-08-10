<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use League\Csv\Writer;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * Exports data to a csv file.
 *
 * @author SFL
 *
 */
class ExportCSV extends ExportDataFile
{

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
                $header[] = $col->getName();
            }
        }
        $this->getWriter()->insertOne($header);
        return $header;
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
    protected function writeFileResult()
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
}
?>