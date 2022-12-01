<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iCanUseProxyFacade;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;

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
     * @see axenox\Proxy\Facades\ProxyFacade for common examples.
     * 
     * @uxon-property use_proxy
     * @uxon-type boolean
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iCanUseProxyFacade::setUseProxy()
     */
    public function setUseProxy(bool $trueOrFalse) : WidgetInterface
    {
        $this->useProxy = $trueOrFalse;
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
     * @return HttpFacadeInterface
     */
    protected function getProxyFacade() : HttpFacadeInterface
    {
        return FacadeFactory::createFromString("axenox.Proxy.ProxyFacade", $this->getWorkbench());
    }
    
}