<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
interface iCanUseProxyTemplate extends WidgetInterface
{
    /**
     * 
     * @return bool
     */
    public function getUseProxy() : bool;
    
    /**
     * 
     * @param bool|int|string $trueOrFalse
     * @return iCanUseProxyTemplate
     */
    public function setUseProxy($trueOrFalse) : iCanUseProxyTemplate;
}