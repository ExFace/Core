<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Facades\FacadeInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface for events triggered when processing HTTP requests.
 * 
 * Provides non-modifying access to the PSR-15 request instance.
 * 
 * @author Andrej Kabachnik
 *
 */
interface HttpRequestEventInterface extends EventInterface
{
    /**
     * Returns the HTTP request.
     * 
     * @return FacadeInterface
     */
    public function getRequest() : ServerRequestInterface;
}