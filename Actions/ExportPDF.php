<?php

namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Data;
use exface\Core\Actions\Traits\iCreatePdfTrait;
use exface\Core\Interfaces\Actions\iCreatePdf;

class ExportPDF extends ExportJSON implements iCreatePdf
{
    use iCreatePdfTrait;
    
    private $contentHtml = null;    
    
    public function getMimeType() : ?string
    {
        if ($this->mimeType === null && get_class($this) === ExportPDF::class) {
            return 'application/pdf';
        }
        return $this->mimeType;
    }
    
    /**
     *
     * @return string
     */
    protected function getFileExtension() : string
    {
        return 'pdf';
    }
    
    /**
     * Generates an array of column names from the passed array of widgets.
     *
     * The column name array is returned.
     *
     * @param WidgetInterface $widget
     * @return string[]
     */
    protected function writeHeader(WidgetInterface $exportedWidget) : array
    {
        $contentHtml = $this->writeHtmlBegin($exportedWidget);
        $columnNames = parent::writeHeader($exportedWidget);
        $contentHtml .= <<<HTML
            <table style="border-collapse: collapse; border: 0.5pt solid black; width: 100%">
                <thead>
                    <tr>
HTML;
        foreach ($columnNames as $name) {
            $contentHtml .= "<th>{$name}</th>";
        }
        $contentHtml .= <<<HTML
        
                    </tr>
                </thead>
                <tbody>
HTML;
        $this->contentHtml = $contentHtml;
        return $columnNames;
    }
    
    protected function writeHtmlBegin(WidgetInterface $exportedWidget) : string
    {
        $count = 0;
        if ($exportedWidget instanceof Data) {
            foreach ($exportedWidget->getFilters() as $filter_widget) {
                if ($filter_widget->getValue()) {
                    $count++;
                }
            }
        }
        $date = date("m.d.Y");
        if ($this->contentHtml === null) {
            $this->contentHtml = <<<HTML
        
<head>
        <style>
            @page {
                margin: 100px 28px;
            }
            header {
                position: fixed;
                top: -60px;
                left: 0px;
                right: 0px;
                height: 50px;
            }

            footer {
                position: fixed; 
                bottom: -60px; 
                left: 0px; 
                right: 0px;
                height: 50px;
            }
		</style>
    </head>
    <body>
        <!-- Define header and footer blocks before your content -->
        <header>
        <div style="text-align: center;"><span style="color:gray; font-size:0.8em;">{$exportedWidget->getMetaObject()->getName()} ({$exportedWidget->getMetaObject()->getAliasWithNamespace()})</span></div>
        <hr style="height:2px; border-width:0; color:gray; background-color:gray">
        </header>

        <footer>
            <hr style="height:2px; border-width:0; color:gray; background-color:gray">
            <table style="width: 100%; font-size:0.8em;">
                <tbody>
                    <tr>
                        <td style="border: none; width: 25%"><span style="color:gray;">{$this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getName()}</span></td>
                        <td style="border: none; width: 25%"><span style="color:gray;">{$date}</span></td>
                        <td style="border: none; width: 25%"><span style="color:gray;">Filter: {$count}</span></td>
                        <td style="border: none; width: 25%; text-align: right;"><span class="page-number" style="color:gray;">Page </span></td>
                    </tr>
                </tbody>
            </table>
        </footer>
        <main>
            <style type="text/css">
                td {padding: 5px; border: 0.5pt solid black;}
                th {padding: 5px; border: 0.5pt solid black;}
                .page-number:after { content: counter(page); }
            </style>            
HTML;
        }
        return $this->contentHtml;
    }
    
    /**
     * Generates rows from the passed DataSheet and writes them as html table rows.
     *
     * The cells of the row are added in the order specified by the passed columnNames array.
     * Cells which are not specified in this array won't appear in the result output.
     *
     * @param DataSheetInterface $dataSheet
     * @param string[] $columnNames
     * @return string
     */
    protected function writeRows(DataSheetInterface $dataSheet, array $columnNames)
    {
        $contentHtml = $this->contentHtml;
        $rowsHtml = "";
        foreach ($dataSheet->getRows() as $row) {
            $outRow = [];
            foreach ($columnNames as $key => $value) {
                $outRow[$key] = $row[$key];
            }
            $rowsHtml .= "<tr>";
            foreach ($outRow as $value) {
                $rowsHtml .= "<td>{$value}</td>";
            }
            $rowsHtml .= "</tr>";
        }
        $contentHtml .= $rowsHtml;
        $contentHtml .= <<<HTML
                </tbody>
            </table>
HTML;
        $this->contentHtml = $contentHtml;
        return;
    }
    
    /**
     * Writes the terminated file to the path from getFilePathAbsolute().
     *
     * @param DataSheetInterface $dataSheet
     * @return void
     */
    protected function writeFileResult(DataSheetInterface $dataSheet)
    {
        $contentHtml = $this->contentHtml;
        $contentHtml .= $this->buildFilterLegendHtml($dataSheet);
        $contentHtml .= <<<HTML
        </main>
    </body>
</html>
HTML;
        $filecontent = $this->createPdf($contentHtml, $this->getOrientation());
        fwrite($this->getWriter(), $filecontent);
        fclose($this->getWriter());
    }
    
    protected function buildFilterLegendHtml(DataSheetInterface $dataSheet) : string
    {
        $html = '';
        $filterData = $this->getFilterData($dataSheet);
        if (empty($filterData)) {
            return $html;
        }
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $html = <<<HTML
            <div style="page-break-before: always;">
                <h2>{$translator->translate('ACTION.EXPORTXLSX.FILTER')}</h2>
 
                <table style="border-collapse: collapse; border: 0.5pt solid black; width: 100%">                    
                    <tbody>
                    
HTML;
        foreach ($filterData as $key => $value) {
            $html .= <<<HTML
                        <tr>
                            <td style="width: 25%; padding: 5px; overflow:hidden;">{$key}</td>
                            <td style="width: 75%; padding: 5px; overflow:hidden;">{$value}</td>
                        </tr>
HTML;
        }
        $html .= <<<HTML
                    </tbody>
                </table>
            </div>
HTML;
        return $html;
    }
    
    /**
     * 
     * @return resource
     */
    protected function getWriter()
    {
        if (is_null($this->writer)) {
            $this->writer = fopen($this->getFilePathAbsolute(), 'x+');
        }
        return $this->writer;
    }
}