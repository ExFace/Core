<?php
namespace exface\Core\Actions;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\NumberEnumDataType;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iCanBeAligned;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Value;

/**
 * Exports data to a printable HTML table and automatically open the browsers printing prompt
 * 
 * 
 * 
 *  
 * @author Andrej Kabachnik
 *
 */
class ExportPrint extends ExportJSON
{
    private array $alignedCols = [];
    private $writer = null;
    private bool $autoPrint = true;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::PRINT_);
    }

    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        /** @var $resultFile \exface\Core\CommonLogic\Tasks\ResultFile */
        $resultFile = parent::perform($task, $transaction);
        $fileInfo = $resultFile->getFileInfo();
        
        $url = HttpFileServerFacade::buildUrlToViewFile($this->getWorkbench(), $fileInfo, true);
        if (null === $message = $this->getResultMessageText()) {
            $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.EXPORTPRINT.RESULT');
        }
        $resultLink = ResultFactory::createUriResult($task, $url, $message);
        $resultLink->setDownload(false);
        $resultLink->setOpenInNewWindow(true);
        return $resultLink;
    }

    protected function getWriter()
    {
        if (is_null($this->writer)) {
            $handle = fopen($this->getFilePathAbsolute(), 'x+');
            if ($handle === false) {
                throw new ActionRuntimeError($this, 'Cannot write temp. export file "' . $this->getFilePathAbsolute() . '".');
            } else {
                $this->writer = $handle;
            }
            
            $javascript = '';
            if ($this->willOpenPrintPrompt()) {
                $javascript = <<<JS

    window.onload = function () {
        print();
    };
JS;

            }
            
            fwrite($this->writer, <<<HTML
<!DOCTYPE html>
<html lang="{$this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale()}">
    <head>
        <meta charset="UTF-8">
        <title>{$this->getFilename()}</title>
        <style type="text/css">
        
            table {border-collapse: collapse;}
            thead {display: table-header-group;}
            tfoot {display: table-footer-group;}
            td, th {border: 1px solid lightgray; padding: 2px 5px;}
            th {hyphens: auto;}
        </style>
        <script type="text/javascript">
            {$javascript}
        </script>
    </head>
    <body>
        <table>
HTML);
        }
        return $this->writer;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeHeader()
     */
    protected function writeHeader(array $exportedColumns) : array
    {
        $colNames = [];
        $indexes = [];
        $htmlRow = '';
        $fileHandle = $this->getWriter();
        foreach ($exportedColumns as $widget) {
            if ($widget instanceof iShowDataColumn && $widget->isExportable(true) === false) {
                continue;
            }
            if ($widget->isHidden()) {
                continue;
            }
            // Name der Spalte
            if ($this->getUseAttributeAliasAsHeader() === true && ($widget instanceof iShowDataColumn) && $widget->isBoundToDataColumn()) {
                $colHeader = $widget->getAttributeAlias();
            } else {
                $colHeader = $widget->getCaption();
            }
            $colId = $widget->getDataColumnName();

            if ($colHeader === '' || $colHeader === null) {
                $colHeader = $colId;
            }
            
            // See if we need some special alignment
            $align = $this->buildCssAlign($widget);
            if ($align !== null) {
                $this->alignedCols[$colId] = $align;
            }
            
            // The name of the column should be unique
            $idx = ($indexes[$colHeader] ?? 0) + 1;
            $indexes[$colHeader] = $idx;
            if ($idx > 1) {
                $colHeader .= ' (' . $idx . ')';
            }
            
            $htmlRow .= <<<HTML

                    <th>{$colHeader}</th>                
HTML;
            
            $colNames[] = $colId;
        }
        fwrite($fileHandle, <<<HTML

            <thead>
                <tr>
                    {$htmlRow}
                </tr>
            </thead>
            <tbody>    
HTML);
        return $colNames;
    }

    /**
     * @param WidgetInterface $widget
     * @return string|null
     */
    protected function buildCssAlign(WidgetInterface $widget) : ?string
    {
        $valueWidget = $widget instanceof DataColumn ? $widget->getCellWidget() : $widget;
        if ($valueWidget instanceof iCanBeAligned) {
            $align = null;
            switch ($valueWidget->getAlign()) {
                case EXF_ALIGN_CENTER:
                    $align = 'center';
                    break;
                case EXF_ALIGN_RIGHT:
                case EXF_ALIGN_OPPOSITE:
                    $align = 'center';
                    break;
            }
        }
        if ($align === null && $valueWidget instanceof Value) {
            $dataType = $valueWidget->getValueDataType();
            switch (true) {
                case $dataType instanceof NumberDataType:
                    $align = 'right';
                    break;
                case $dataType instanceof BooleanDataType:
                    $align = 'center';
                    break;
            }
        }
        return $align;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeRows()
     */
    protected function writeRows(DataSheetInterface $dataSheet, array $headerKeys)
    {
        $fileHandle = $this->getWriter();
        foreach ($dataSheet->getRows() as $row) {
            $htmlRow = '';
            foreach ($headerKeys as $key) {
                $style = '';
                if (null !== $align = $this->alignedCols[$key]) {
                    $style = 'text-align:' . $align . ';';
                }
                $htmlRow .= <<<HTML

                    <td style="{$style}">{$row[$key]}</td>
HTML;
            }
            fwrite($fileHandle, <<<HTML

                <tr>
                    {$htmlRow}
                </tr>
HTML);
        }
    }

    /**
     * Writes the terminated file to the path from getFilePathAbsolute().
     *
     * @param DataSheetInterface $dataSheet
     * @return void
     */
    protected function writeFileResult(DataSheetInterface $dataSheet)
    {
        fwrite($this->getWriter(), <<<HTML

            </tbody>
        </table>
    </body>
</html>
HTML);
        fclose($this->getWriter());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::getMimeType()
     */
    public function getMimeType() : ?string
    {
        return 'text/html';
    }

    /**
     * Set to FALSE to just show the printed data without opening the browser print prompt
     * 
     * @uxon-property open_print_prompt
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return $this
     */
    protected function setOpenPrintPrompt(bool $trueOrFalse) : ExportPrint
    {
        $this->autoPrint = $trueOrFalse;
        return $this;
    }

    /**
     * @return bool
     */
    protected function willOpenPrintPrompt() : bool
    {
        return $this->autoPrint;
    }
}