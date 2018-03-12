<?php
namespace exface\Core\Interfaces\Tasks;

use Psr\Http\Message\ServerRequestInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface HttpTaskInterface extends TaskInterface
{    
    /**
     * 
     * @return ServerRequestInterface
     */
    public function getHttpRequest() : ServerRequestInterface;
}