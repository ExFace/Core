<?php
namespace exface\Core\Facades;

use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Factories\PermalinkFactory;
use exface\Core\Interfaces\Permalinks\PermalinkInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Redirects the browser to a dynamic URL from a long-term link.
 * 
 * ## Link Syntax:
 * 
 * **General:**
 * - `api/permalink/<config_alias>/[parameters]`
 *     
 * **Opening the object editor:**
 * - `api/permalink/exface.Core.show_object/[target_uid]` - open the object editor
 * 
 * **Running a DataFlow with parameters:**
 * - `api/permalink/my.app.run_data_flow/<uid_of_flow>/<param1>/<param2>` - run a DataFlow with parameters
 * 
 * @author Andrej Kabachnik
 *
 */
class PermalinkFacade extends AbstractHttpFacade
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponse()
     */
    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {
        // Parse URL.
        $requestUri = $request->getUri();
        $urlPath = $requestUri->getPath();
        
        // Create instance.
        $permalink = PermalinkFactory::fromUrlOrSelector($this->getWorkbench(), $urlPath);
        
        // Update headers.
        $headers = $this->buildHeadersCommon();
        $headers['Location'] = $permalink->buildAbsoluteRedirectUrl();

        return new Response(PermalinkInterface::REDIRECT_STATUS_CODE, $headers);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::buildHeadersCommon()
     */
    protected function buildHeadersCommon() : array
    {
        try {
            $facadeHeaders = array_filter($this->getConfig()->getOption('FACADES.PERMALINKFACADE.HEADERS.COMMON')->toArray());
        } catch (ConfigOptionNotFoundError $e) {
            $facadeHeaders = [];
        }
        $commonHeaders = parent::buildHeadersCommon();
        return array_merge($commonHeaders, $facadeHeaders);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault(): string
    {
        return PermalinkInterface::API_ROUTE;
    }
}