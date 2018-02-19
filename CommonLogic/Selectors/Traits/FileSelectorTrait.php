<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Interfaces\Selectors\FileSelectorInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Workbench;

/**
 * Trait with shared logic for the FileSelectorInterface
 *
 * @author Andrej Kabachnik
 *
 */
trait FileSelectorTrait
{
    private $isFilepath = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\FileSelectorInterface::isFilepath()
     */
    public function isFilepath()
    {
        // If the string contains "/" or "\" (but the first character is not "\") and also contains ".php" - treat it as a file name
        if (is_null($this->isFilepath)) {
            $string = $this->toString();
            $extension = strtolower(pathinfo($string, PATHINFO_EXTENSION));
            $this->isFilepath = (mb_strpos($string, DIRECTORY_SEPARATOR) > 0 || mb_strpos($string, FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR) !== false) && $extension === FileSelectorInterface::PHP_FILE_EXTENSION ? true : false;
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