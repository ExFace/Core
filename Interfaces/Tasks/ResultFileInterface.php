<?php
namespace exface\Core\Interfaces\Tasks;

use exface\Core\Exceptions\RuntimeException;

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
     * @return string
     */
    public function getPathAbsolute() : string;
    
    /**
     * Sets the path to the result file: either absolute or relative to the installation folder.
     * 
     * @param string $path
     * @return ResultFileInterface
     */
    public function setPath(string $path) : ResultFileInterface;
    
    /**
     * Returns the contents of the file as a string
     * 
     * @throws RuntimeException
     * @return string
     */
    public function getContents() : string;
    
    /**
     * Returns the file as a resource - like fopen()
     *  
     * @param string $mode
     * @throws RuntimeException
     * @return resource
     */
    public function getResourceHandle(string $mode = "r");
}