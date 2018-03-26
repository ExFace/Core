<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Interfaces\Selectors\FileSelectorInterface;
use exface\Core\CommonLogic\Filemanager;

/**
 * Trait with shared logic for the FileSelectorInterface.
 *
 * @author Andrej Kabachnik
 *
 */
trait FileSelectorTrait
{
    private $isFilepath = null;
    
    /**
     * {@inheritDoc}
     * 
     * A file path must end with the PHP extension ".php".
     * 
     * @see \exface\Core\Interfaces\Selectors\FileSelectorInterface::isFilepath()
     */
    public function isFilepath()
    {
        // If the string contains ends with ".php" - treat it as a file name
        if ($this->isFilepath === null) {
            $ext = '.' . FileSelectorInterface::PHP_FILE_EXTENSION;
            $this->isFilepath = (strtolower(substr($this->toString(), (-1*strlen($ext)))) === $ext);
        }
        return $this->isFilepath;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\FileSelectorInterface::getPathAbsolute()
     */
    public function getPathAbsolute()
    {
        return Filemanager::pathJoin([$this->getWorkbench()->filemanager()->getPathToVendorFolder(), $this->getPathRelativeToVendorFolder()]);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\FileSelectorInterface::getFolderAbsolute()
     */
    public function getFolderAbsolute()
    {
        return pathinfo($this->getPathAbsolute(), PATHINFO_DIRNAME);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\FileSelectorInterface::getFolderRelativeToVendorFolder()
     */
    public function getFolderRelativeToVendorFolder()
    {
        return pathinfo($this->getPathRelativeToVendorFolder(), PATHINFO_DIRNAME);
    }
}