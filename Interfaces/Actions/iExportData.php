<?php
namespace exface\Core\Interfaces\Actions;

/**
 * Actions, that export data should implement this interface.
 *
 * @author Andrej Kabachnik
 *        
 */
interface iExportData extends iReadData
{
    /**
     * Returns FALSE if the exported data should not be downloadable. TRUE by default.
     * 
     * @return boolean
     */
    public function getDownload();
    
    /**
     * 
     * @param boolean $download
     * @return iExportData
     */
    public function setDownload($true_or_false);
    
    /**
     * @return string
     */
    public function getFilename();
    
    /**
     * @param string $filename
     * @return iExportData
     */
    public function setFilename($filename);
    
    /**
     * 
     * @return string
     */
    public function getMimeType();
    
    /**
     * 
     * @param unknown $mimeType
     * @return iExportData
     */
    public function setMimeType($mimeType);
}