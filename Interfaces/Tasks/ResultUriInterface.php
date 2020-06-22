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
    public function getAutoRedirect() : bool;
    
    /**
     * 
     * @param bool $trueOrFalse
     * @return ResultUriInterface
     */
    public function setAutoRedirect($trueOrFalse) : ResultUriInterface;
    
    /**
     * 
     * @return bool
     */
    public function getOpenInNewWindow() : bool;
    
    /**
     * 
     * @param unknown $trueOrFalse
     * @return ResultUriInterface
     */
    public function setOpenInNewWindow($trueOrFalse) : ResultUriInterface;
    
}