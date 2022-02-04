<?php
namespace exface\Core\Actions\Traits;

use Dompdf\Dompdf;
use exface\Core\Interfaces\Actions\iCreatePdf;

trait iCreatePdfTrait
{
    private $orientation = 'landscape';
    
    /**
     * @uxon-property orientation
     * @uxon-type [portrait,landscape]
     * @uxon-required true
     *
     * @param string $value
     * @return iCreatePdfTrait
     */
    public function setOrientation(string $value) : iCreatePdf
    {
        $this->orientation = $value;
        return $this;
    }
    
    public function getOrientation() : string
    {
        return $this->orientation;
    }
    
    /**
     * Returns dompdf->output() stream to save in a file.
     *
     * @param string $contentHtml
     * @return unknown
     */
    public function createPdf(string $contentHtml)
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
        $dompdf->setPaper('A4', $this->getOrientation());
        
        // Render the HTML as PDF
        $dompdf->render();
        return $dompdf->output();
    }
    
}