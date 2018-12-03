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
    public function isDownloadable() : bool;
    
    /**
     * 
     * @param boolean $download
     * @return iExportData
     */
    public function setDownloadable($true_or_false) : iExportData;
    
    /**
     * @return string
     */
    public function getFilename() : string;
    
    /**
     * @param string $filename
     * @return iExportData
     */
    public function setFilename(string $filename) : iExportData;
    
    /**
     * 
     * @return string|NULL
     */
    public function getMimeType() : ?string;
    
    /**
     * 
     * @param string $mimeType
     * @return iExportData
     */
    public function setMimeType(string $mimeType) : iExportData;
}