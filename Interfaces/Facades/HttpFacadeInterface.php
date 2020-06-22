<?php
namespace exface\Core\Interfaces\Facades;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface HttpFacadeInterface extends FacadeInterface, RequestHandlerInterface
{    
    
    /**
     * Returns the absolute URL to the workbench router.
     * 
     * E.g. https://www.mydomain.com or https://www.mydomain.com/subfolder if the workbench
     * is installed in a subfolder.
     * 
     * @return string
     */
    public function buildUrlToSiteRoot() : string;
    
    /**
     * Returns the URL to access the facade directly.
     * 
     * E.g. `http://www.exface.com/subfolder/api/docs` or `api/docs` for the `DocsFacade` 
     * depending on the $relativeToSiteRoot parameter.
     * 
     * @param bool $relativeToSiteRoot
     * @return string
     */
    public function buildUrlToFacade(bool $relativeToSiteRoot = false) : string;
    
    /**
     * Returns an array of regular expressions to check if a give route belongs to
     * this facade.
     * 
     * These patterns are used by the workbench's HTTP router: it will pass a request
     * to this facade if at least on regular expression matches the requested URL.
     * 
     * @return string[]
     */
    public function getUrlRoutePatterns() : array;
}
