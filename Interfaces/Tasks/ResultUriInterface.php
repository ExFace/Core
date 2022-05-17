<?php
namespace exface\Core\Interfaces\Tasks;

use Psr\Http\Message\UriInterface;

/**
 * Interfaces for task results of actions, that produce widgets.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ResultUriInterface extends ResultInterface
{    
    /**
     * 
     * @return UriInterface
     */
    public function getUri() : UriInterface;
    
    /**
     * 
     * @param UriInterface|string $uri
     * @return ResultUriInterface
     */
    public function setUri($uri) : ResultUriInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasUri() : bool;    
    
    /**
     * 
     * @return bool
     */
    public function isAutoRedirect() : bool;
    
    /**
     * 
     * @param bool $trueOrFalse
     * @return ResultUriInterface
     */
    public function setAutoRedirect(bool $trueOrFalse) : ResultUriInterface;
    
    /**
     * 
     * @return bool
     */
    public function isOpenInNewWindow() : bool;
    
    /**
     * 
     * @param bool $trueOrFalse
     * @return ResultUriInterface
     */
    public function setOpenInNewWindow(bool $trueOrFalse) : ResultUriInterface;
    
    /**
     * 
     * @return bool
     */
    public function isDownload() : bool;
    
    /**
     * 
     * @param bool $value
     * @return ResultUriInterface
     */
    public function setDownload(bool $value) : ResultUriInterface;
}