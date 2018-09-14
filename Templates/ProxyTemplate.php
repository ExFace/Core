<?php
namespace exface\Core\Templates;

use exface\Core\Templates\AbstractTemplate\AbstractTemplate;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\Psr7\stream_for;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

/**
 * This template act's as a proxy: it fetches and passes along data located at the URI in the request parameter "url".
 * 
 * This can be use for cross-origin AJAX requests or HTTP-requests on an HTTPS-server. Concider the following examples:
 * 
 * The client uses a secure HTTPS-conection, but the data source works with HTTP. If the data fetched from the data
 * source contains a resource URI (e.g. an image), the `Image` widget would normally just tell the client browser
 * to fetch the image from the given URI. This will fail because the browser would not want an HTTP-image in an
 * HTTPS-page. Since we trust the data source, a solution may be to wrap the real resource URI in a call to the
 * proxy template: https://my.plattform.serv/api/proxy?url=[urlencodedURI]. Now the browser will request a safe
 * resource from our server, which in-turn will fetch it from the data source and pass the result on to the
 * browser.
 * 
 * A similar situation occurs if a data source checks the origin of requests and does not allow certain resource
 * types (again, images), to be requested separately: e.g. an image cannot be loaded if the corresponding web page
 * was not requested from the same origin previously. When fetching data from websites with this kind of restrictions 
 * via the UrlDataConnector, we can't let the browser fetch resources directly, but can tunnel requests through
 * the ProxyTemplate, which would be the same origin as the data reading request from the connector.
 * 
 * Last, but not least, using the ProxyTemplate, an additional caching layer may be implemented, allowing to reduce
 * the load on external servers or even fetch resources if the original data source is not accessible (e.g. down). 
 * 
 * TODO The caching functionality is not implemented yet.
 * 
 * @author Andrej Kabachnik
 *
 */
class ProxyTemplate extends AbstractTemplate implements HttpTemplateInterface
{    
    private $url = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $url = $request->getQueryParams()[url];
        $method = $request->getMethod();
        $requestHeaders = $request->getHeaders();
        
        $client = new Client();
        $result = $client->request($method, $url, ['headers' => $requestHeaders]);
        
        $responseHeaders = $result->getHeaders();
        unset($responseHeaders['Transfer-Encoding']);
        $response = new Response($result->getStatusCode(), $responseHeaders, (string) $result->getBody(), $result->getProtocolVersion(), $result->getReasonPhrase());
        
        return $response;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Templates\HttpTemplateInterface::getUrlRoutePatterns()
     */
    public function getUrlRoutePatterns() : array
    {
        return [
            "/\/api\/proxy[\/?]/"
        ];
    }
    
    /**
     *
     * @return string
     */
    public function getBaseUrl() : string{
        if (is_null($this->url)) {
            if (! $this->getWorkbench()->isStarted()) {
                $this->getWorkbench()->start();
            }
            $this->url = $this->getWorkbench()->getCMS()->buildUrlToApi() . '/api/proxy';
        }
        return $this->url;
    }
    
    /**
     * 
     * @param string $uri
     * @return string
     */
    public function getProxyUrl(string $uri) : string
    {
        return $this->getBaseUrl() . '?url=' . urlencode($uri);
    }
}