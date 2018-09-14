<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Interfaces\Selectors\FileSelectorInterface;

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
}