<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors based on file paths.
 * 
 * @author Andrej Kabachnik
 *
 */
interface FileSelectorInterface extends SelectorInterface
{
    const NORMALIZED_DIRECTORY_SEPARATOR = '/';
    
    const PHP_FILE_EXTENSION = 'php';

    /**
     * Returns the path relative to the vendor folder of the plattform.
     * 
     * @return string
     */
    public function getPathRelativeToVendorFolder();
    
    /**
     * Returns the absolute path of the file.
     * 
     * @return string
     */
    public function getPathAbsolute();
    
    /**
     * Returns TRUE if this selector is based on a file path and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isFilepath();
}