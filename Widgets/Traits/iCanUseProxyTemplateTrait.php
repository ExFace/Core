<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Widgets\iCanUseProxyFacade;
use exface\Core\Facades\ProxyFacade;
use exface\Core\Factories\FacadeFactory;

trait iCanUseProxyFacadeTrait {
    
    private $useProxy = false;
    
    private $proxy = null;
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iCanUseProxyFacade::getUseProxy()
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
     * @see ProxyFacade for common examples.
     * 
     * @uxon-property use_proxy
     * @uxon-type boolean
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iCanUseProxyFacade::setUseProxy()
     */
    public function setUseProxy($trueOrFalse) : iCanUseProxyFacade
    {
        $this->useProxy = BooleanDataType::cast($trueOrFalse);
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iCanUseProxyFacade::buildProxyUrl()
     */
    public function buildProxyUrl(string $uri) : string
    {
        return $this->getProxyFacade()->getProxyUrl($uri);
    }
    
    /**
     * 
     * @return ProxyFacade
     */
    protected function getProxyFacade() : ProxyFacade
    {
        return FacadeFactory::createFromString(ProxyFacade::class, $this->getWorkbench());
    }
    
}