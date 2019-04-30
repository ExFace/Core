<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use exface\Core\Facades\AbstractFacade\AbstractFacade;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;

/**
 * Common base structure for HTTP facades.
 *
 * Provides methods to register routes and generate URLs.
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractHttpFacade extends AbstractFacade implements HttpFacadeInterface
{
    private $url;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\HttpFacadeInterface::buildUrlToFacade()
     */
    public function buildUrlToFacade() : string
    {
        if ($this->url === null) {
            if (! $this->getWorkbench()->isStarted()) {
                $this->getWorkbench()->start();
            }
            $this->url = $this->getWorkbench()->getCMS()->buildUrlToRouter() . '/' .  $this->getUrlRouteDefault();
        }
        return $this->url;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\HttpFacadeInterface::getUrlRoutePatterns()
     */
    public function getUrlRoutePatterns() : array
    {
        return [
            '/' . preg_quote($this->getUrlRouteDefault() . '[/?]', '/') . '/'
        ];
    }
    
    /**
     * Returns the default route to the pattern: e.g. "api/docs" for the DocsFacade.
     * @return string
     */
    abstract public function getUrlRouteDefault() : string;
}