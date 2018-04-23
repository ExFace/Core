<?php
namespace exface\Core\Interfaces\Templates;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface HttpTemplateInterface extends TemplateInterface, RequestHandlerInterface
{
    public function getBaseUrl() : string;
    
    public function getUrlRoutePatterns() : array;
}
?>