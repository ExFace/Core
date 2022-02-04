<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Actions\Traits\iCreatePdfTrait;
use exface\Core\Interfaces\Actions\iCreatePdf;

class PrintPdf extends PrintTemplate implements iCreatePdf
{
    use iCreatePdfTrait;
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputData = $this->getInputDataSheet($task);
        $contents = $this->renderHtmlContents($inputData);
        foreach ($contents as $html) {
            file_put_contents($this->getFilePathAbsolute(), $this->createPdf($html));
        }
        $result = ResultFactory::createFileResult($task, $this->getFilePathAbsolute());
        
        return $result;
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