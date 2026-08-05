<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use exface\Core\Interfaces\WorkbenchInterface;
use Psr\Http\Message\UriInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Exceptions\Facades\FacadeRoutingError;
use exface\Core\Factories\FacadeFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\UiPage\UiPageNotFoundError;
use exface\Core\Interfaces\Model\UiPageInterface;

/**
 * Parses a given URI and instantiates the addressed facade and page if applicable
 * 
 * @author Andrej Kabachnik
 *
 */
class FacadeResolver
{
    private WorkbenchInterface $workbench;
    private UriInterface $uri;
    
    private ?HttpFacadeInterface $facade = null;
    private ?UiPageInterface $uiPage = null;

    /**
     *
     * @param WorkbenchInterface $workbench
     * @param UriInterface $uri
     */
    public function __construct(WorkbenchInterface $workbench, UriInterface $uri)
    {
        $this->workbench = $workbench;
        $this->uri = $uri;
    }
    
    public function getFacade() : HttpFacadeInterface
    {
        if ($this->facade === null) {
            $this->parseUri($this->uri);
        }
        return $this->facade;
    }
    
    public function getPage() : ?UiPageInterface
    {
        if ($this->uiPage === null && $this->facade === null) {
            $this->parseUri($this->uri);
        }
        return $this->uiPage;
    }
    
    protected function parseUri(UriInterface $uri) : FacadeResolver
    {
        $facade = $this->getFacadeFromRoutesConfig($uri);
        if ($facade === null) {
            $this->uiPage = $this->getPageFromUri($uri);
            $facade = $this->uiPage->getFacade();
        }
        $this->facade = $facade;
        return $this;
    }
    
    /**
     * Searches for UI pages with aliases matching the URI
     * 
     * @throws UiPageNotFoundError
     * @return UiPageInterface
     */
    protected function getPageFromUri() : UiPageInterface
    {
        $uri = $this->uri;
        // If not, see if the URL matches a page alias
        // Get the last part of the path in the URI
        $aliasFromUrl = StringDataType::substringAfter($uri->getPath(), '/', '', false, true);
        // Remove the file extension
        $aliasFromUrl = StringDataType::substringBefore($aliasFromUrl, '.', $aliasFromUrl, false, true);
        
        if ($aliasFromUrl === '') {
            return $this->workbench->getSecurity()->getAuthenticatedUser()->getStartPage();
        } else {
            return UiPageFactory::createFromModel($this->workbench, $aliasFromUrl);
        }
    }

    /**
     * Matches the URI against FACADES.ROUTES config and returns the matching facade or NULL if no match.
     *
     * @return HttpFacadeInterface|NULL
     */
    public function getFacadeFromRoutesConfig() : ?HttpFacadeInterface
    {
        $uri = $this->uri;
        $url = $uri->getPath() . '?' . $uri->getQuery();
        $routes = $this->workbench->getConfig()->getOption('FACADES.ROUTES');
        if ($routes->isEmpty()) {
            throw new FacadeRoutingError('No route configuration found is system config option FACADES.ROUTES - (re)install at least one facade!');
        }
        foreach ($routes as $pattern => $facadeAlias) {
            if (preg_match($pattern, $url) === 1) {
                return FacadeFactory::createFromString($facadeAlias, $this->workbench);
            }
        }
        return null;
    }
}