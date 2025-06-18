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
    /**
     * Get the URL this permalink redirects to.
     * 
     * For example in `PermalinkFacade::createResponse()`:
     * 
     * ```
     *  ...
     * 
     *  $headers = $this->buildHeadersCommon();
     *  $headers['Location'] = $this->getWorkbench()->getUrl() . $permalink->getRedirect();
     *  $response = new Response(301, $headers);
     * 
     *  return $response;
     * }
     * 
     * ```
     * 
     * @return string
     */
    public function getRedirect() : string;

    /**
     * @return string
     */
    public function __toString() : string;

    /**
     * Parses the inner URL provided and returns a new permalink instance based on the results.
     * 
     * @param string $innerUrl
     * @return PermalinkInterface
     */
    public function withUrl(string $innerUrl) : PermalinkInterface;
}