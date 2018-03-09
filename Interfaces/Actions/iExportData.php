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
    public function isDownloadable();
    
    /**
     * 
     * @param boolean $download
     * @return iExportData
     */
    public function setDownloadable($true_or_false) : iExportData;
    
    /**
     * @return string
     */
    public function getFilename();
    
    /**
     * @param string $filename
     * @return iExportData
     */
    public function setFilename($filename) : iExportData;
    
    /**
     * 
     * @return string
     */
    public function getMimeType();
    
    /**
     * 
     * @param string $mimeType
     * @return iExportData
     */
    public function setMimeType($mimeType) : iExportData;
}