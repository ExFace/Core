<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
interface iCanUseProxyFacade extends WidgetInterface
{
    /**
     * 
     * @return bool
     */
    public function getUseProxy() : bool;
    
    /**
     * 
     * @param bool|int|string $trueOrFalse
     * @return WidgetInterface
     */
    public function setUseProxy(bool $trueOrFalse) : WidgetInterface;
    
    /**
     *
     * @param string $uri
     * @return string
     */
    public function buildProxyUrl(string $uri) : string;
}