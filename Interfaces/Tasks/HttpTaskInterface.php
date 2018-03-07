<?php
namespace exface\Core\Interfaces\Tasks;

use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Templates\TemplateInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface HttpTaskInterface extends TaskInterface
{
    /**
     * 
     * @param TemplateInterface $template
     * @param ServerRequestInterface $request
     */
    public function __construct(TemplateInterface $template, ServerRequestInterface $request = null);
    
    /**
     * 
     * @return ServerRequestInterface
     */
    public function getHttpRequest() : ServerRequestInterface;
}