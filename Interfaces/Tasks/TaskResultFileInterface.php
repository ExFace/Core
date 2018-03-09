<?php
namespace exface\Core\Interfaces\Tasks;

/**
 * Interfaces for task results of actions, that produce downloadable files.
 * 
 * @author Andrej Kabachnik
 *
 */
interface TaskResultFileInterface extends TaskResultStreamInterface
{    
    /**
     * 
     * @return bool
     */
    public function isDownloadable() : bool;
    
    /**
     * 
     * @return string
     */
    public function getPathAbsolute() : string;
    
    /**
     * Sets the path to the result file: either absolute or relative to the installation folder.
     * 
     * @param string $path
     * @return TaskResultFileInterface
     */
    public function setPath(string $path) : TaskResultFileInterface;
    
}