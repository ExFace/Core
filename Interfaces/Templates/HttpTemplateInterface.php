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
    public function getUrlRoutePatterns() : array;
}
?>