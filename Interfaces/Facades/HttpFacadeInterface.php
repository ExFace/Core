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
     * Returns the URL to access the facade: e.g. http://www.exface.com/demo/api/docs or api/docs
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
