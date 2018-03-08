<?php
namespace exface\Core\Interfaces\Tasks;

use Psr\Http\Message\UriInterface;

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
     * @return UriInterface
     */
    public function getDownloadUri() : UriInterface;
    
    /**
     * 
     * @param UriInterface|string $uriOrString
     * @return TaskResultUriInterface
     */
    public function setDownloadUri($uriOrString) : TaskResultFileInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasDownload() : bool;
    
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