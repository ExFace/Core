<?php

namespace exface\Core\Actions;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Exceptions\Actions\ActionLogicError;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use Dompdf\Dompdf;
use exface\Core\Interfaces\WidgetInterface;

class ExportPDF extends ExportJSON
{
    private $contentHtml = null;
    
    public function getMimeType() : ?string
    {
        if ($this->mimeType === null && get_class($this) === ExportJSON::class) {
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
        $contentHtml = $this->writeHtmlBegin();
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
    
    protected function writeHtmlBegin() : string
    {
        if ($this->contentHtml === null) {
            $this->contentHtml = <<<HTML
        
<html>
    <head>
        <style>
            @page {
                margin: 100px 25px;
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
<header>
 {$this->getWidgetDefinedIn()->getCaption()}
</header>
<footer>
<table style="width: 100%">
<tbody>
  <tr>
    <td style="border: none; width: 25%">User</td>
    <td style="border: none; width: 25%">Datum</td>
    <td style="border: none; width: 25%">Filter</td>
    <td style="border: none; width: 25%"><span class="page-number">Page </span></td>
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
        $this->contentHtml = $contentHtml . $rowsHtml;
        return;
    }
    
    protected function createPdf(string $contentHtml)
    {
        // instantiate and use the dompdf class
        $dompdf = new Dompdf();
        $options = $dompdf->getOptions();
        $options->setDefaultFont('Courier');
        $options->setIsRemoteEnabled(true);
        $options->setIsPhpEnabled(true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($contentHtml);
        
        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('A4', 'landscape');
        
        // Render the HTML as PDF
        $dompdf->render();
        return $dompdf->output();        
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
        $contentHtml .= <<<HTML
                </tbody>
            </table>
        </main>
    </body>
</html>
HTML;
        $filecontent = $this->createPdf($contentHtml);
        fwrite($this->getWriter(), $filecontent);
        fclose($this->getWriter());
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