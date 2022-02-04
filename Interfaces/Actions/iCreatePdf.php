<?php
namespace exface\Core\Interfaces\Actions;

/**
 * Interface for actions that create a pdf file.
 *  
 * 
 * @author ralf.mulansky
 *
 */
interface iCreatePdf
{
    public function setOrientation(string $value) : iCreatePdf;
    
    public function getOrientation() : string;
    
    public function createPdf(string $contentHtml);
}