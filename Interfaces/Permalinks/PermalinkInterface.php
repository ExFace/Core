<?php
namespace exface\Core\Interfaces\Permalinks;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * Permalinks can redirect any request for `api/permalink` to any other part of the app.
 * The redirection target is derived from the configuration of the link and persists through structural app changes.
 */
interface PermalinkInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    public const REDIRECT_STATUS_CODE = 301; // Permanent redirect.
    public const API_ROUTE = 'api/permalink';
    
    /**
     * Returns a relative URL to the destination of this permalink
     * 
     * @return string
     */
    public function buildRelativeRedirectUrl() : string;

    /**
     * Returns an absolute URL to the destination of this permalink within its workbench context.
     * 
     * For example in `PermalinkFacade::createResponse()`:
     * 
     *  ```
     *   ...
     *
     *   $headers = $this->buildHeadersCommon();
     *   $headers['Location'] = $permalink->buildAbsoluteRedirectUrl();
     *   $response = new Response(301, $headers);
     *
     *   return $response;
     *  }
     *
     *  ```
     * 
     * @return string
     */
    public function buildAbsoluteRedirectUrl() : string;

    /**
     * @return string
     */
    public function __toString() : string;

    /**
     * @return string|null
     */
    public function getMockParams() : ?string;

    /**
     * Parses the inner URL provided and returns a new permalink instance based on the results.
     * 
     * @param string $innerUrl
     * @return PermalinkInterface
     */
    public function withUrl(string $innerUrl) : PermalinkInterface;
}