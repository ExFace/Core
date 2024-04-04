<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Facades\FacadeInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface for events triggered when processing HTTP responses.
 * 
 * Provides non-modifying access to the PSR-15 response instance.
 * 
 * @author Andrej Kabachnik
 *
 */
interface HttpResponseEventInterface extends EventInterface
{
    /**
     * Returns the HTTP request.
     * 
     * @return FacadeInterface
     */
    public function getResponse() : ResponseInterface;
}