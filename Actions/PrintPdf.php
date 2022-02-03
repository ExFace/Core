<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;

class PrintPdf extends PrintTemplate
{
    private $orientation = 'portrait';
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputData = $this->getInputDataSheet($task);
        $contents = $this->renderHtmlContents($inputData);
        foreach ($contents as $html) {
            file_put_contents($this->getFilePathAbsolute(), ExportPDF::createPdf($html, $this->getOrientation()));
        }
        $result = ResultFactory::createFileResult($task, $this->getFilePathAbsolute());
        
        return $result;
    }
    
    /**
     * @uxon-property orientation
     * @uxon-type [portrait,landscape]
     * @uxon-required true
     *
     * @param string $value
     * @return PrintPdf
     */
    public function setOrientation(string $value) : PrintPdf
    {
        $this->orientation = $value;
        return $this;
    }
    
    public function getOrientation() : string
    {
        return $this->orientation;
    }
    
    protected function getFileExtension() : string
    {
        return '.pdf';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::getMimeType()
     */
    public function getMimeType() : ?string
    {
        if ($this->mimeType === null && get_class($this) === PrintPdf::class) {
            return 'application/pdf';
        }
        return $this->mimeType;
    }
}