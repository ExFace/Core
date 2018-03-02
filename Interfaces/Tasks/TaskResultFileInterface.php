<?php
namespace exface\Core\Interfaces\Tasks;

use Psr\Http\Message\UriInterface;

/**
 * Interfaces for task results of actions, that produce downloadable files.
 * 
 * @author Andrej Kabachnik
 *
 */
interface TaskResultFileInterface extends TaskResultInterface
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
    
}