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
    public function getRequestAttributeForAction() : string;
    
    public function getRequestAttributeForTask() : string;
    
    public function getRequestAttributeForPage() : string;
    
    public function getRequestAttributeForRenderingMode() : string;
}
?>