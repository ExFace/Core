<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use exface\Core\Facades\AbstractFacade\AbstractFacade;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\DataTypes\StringDataType;

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
    private $urlRelative = null;
    
    private $urlAbsolute = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\HttpFacadeInterface::buildUrlToFacade()
     */
    public function buildUrlToFacade(bool $relativeToSiteRoot = false) : string
    {
        if ($this->urlRelative === null || $this->urlAbsolute === null) {
            if (! $this->getWorkbench()->isStarted()) {
                $this->getWorkbench()->start();
            }
            $cms = $this->getWorkbench()->getCMS();
            $this->urlAbsolute = $cms->buildUrlToRouter() . '/' .  $this->getUrlRouteDefault();
            $this->urlRelative = ltrim(StringDataType::substringAfter($this->urlAbsolute, $cms->buildUrlToSiteRoot(), null) ?? $this->urlAbsolute, "/");
        }
        return $relativeToSiteRoot === true ? $this->urlRelative : $this->urlAbsolute;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\HttpFacadeInterface::getUrlRoutePatterns()
     */
    public function getUrlRoutePatterns() : array
    {
        return [
            '/' . preg_quote('/' . $this->getUrlRouteDefault(), '/') . '[\/?]' . '/'
        ];
    }
    
    /**
     * Returns the default route to the pattern: e.g. "api/docs" for the DocsFacade.
     * @return string
     */
    abstract public function getUrlRouteDefault() : string;
}