<?php
namespace exface\Core\Interfaces\Api;

use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\TemplateInterface;

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
    public function __construct(TemplateInterface $template, ServerRequestInterface $request);
    
    /**
     * 
     * @return ServerRequestInterface
     */
    public function getHttpRequest() : ServerRequestInterface;
}