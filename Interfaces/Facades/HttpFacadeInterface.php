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
     * 
     * @return string
     */
    public function buildUrlToFacade() : string;
    
    /**
     * @return string
     */
    public function getUrlRoutePatterns() : array;
}
