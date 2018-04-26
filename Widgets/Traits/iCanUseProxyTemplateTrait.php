<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Widgets\iCanUseProxyTemplate;
use exface\Core\Templates\ProxyTemplate;

trait iCanUseProxyTemplateTrait {
    
    private $useProxy = false;
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iCanUseProxyTemplate::getUseProxy()
     */
    public function getUseProxy() : bool
    {
        return $this->useProxy;
    }
    
    /**
     * Set to TRUE to make the widget to fetch external resources through a proxy.
     * 
     * In this case, the plattform will act as the only client from the point of
     * view of the resource server. The latter will not know anything about the
     * the actual client consuming the UI.
     * 
     * @see ProxyTemplate for common examples.
     * 
     * @uxon-property use_proxy
     * @uxon-type boolean
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iCanUseProxyTemplate::setUseProxy()
     */
    public function setUseProxy($trueOrFalse) : iCanUseProxyTemplate
    {
        $this->useProxy = BooleanDataType::cast($trueOrFalse);
        return $this;
    }
    
}