<?php
namespace exface\Core\Interfaces\Tasks;

use exface\Core\Interfaces\Filesystem\FileInfoInterface;

/**
 * Interfaces for task results of actions, that produce downloadable files.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ResultFileInterface extends ResultStreamInterface
{    
    /**
     * 
     * @return bool
     */
    public function isDownloadable() : bool;
    
    /**
     *
     * @param bool $trueOrFalse
     * @return ResultFileInterface
     */
    public function setDownloadable(bool $trueOrFalse) : ResultFileInterface;
    
    /**
     * 
     * @return FileInfoInterface
     */
    public function getFileInfo() : FileInfoInterface;
    
    /**
     * 
     * @return string
     */
    public function getContents() : string;
}