<?php
namespace exface\Core\Facades;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use kabachello\FileRoute\FileRouteMiddleware;
use Psr\Http\Message\UriInterface;
use kabachello\FileRoute\Templates\PlaceholderFileTemplate;
use exface\Core\Facades\AbstractHttpFacade\NotFoundHandler;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Facades\DocsFacade\MarkdownDocsReader;
use exface\Core\Facades\DocsFacade\Middleware\AppUrlRewriterMiddleware;
use exface\Core\Facades\AbstractHttpFacade\HttpRequestHandler;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;

/**
 * Renders the markdown docs from the /Docs folder as a simple website
 * 
 * Usage:
 * 
 * - api/docs - render a list of links to all available app docs
 * - api/docs/exface/Core/index.md - render the index page for the Core app docs
 * - api/docs/exface/Core/index.md?render=print - render a printable version of the core docs with all subpages
 * 
 * @author Andrej Kabachnik
 *
 */
class DocsFacade extends AbstractHttpFacade
{
    const URL_PARAM_RENDER = 'render';

    const URL_PARAM_RENDER_PRINT = 'print';

    const URL_PARAM_RENDER_CHAPTER = 'chapter';
    
    private $processedLinks = [];
    private $processedLinksKey = 0;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponse()
     */
    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {
        $handler = new HttpRequestHandler(new NotFoundHandler());
        
        // Add URL rewriter: it will take care of URLs after the content had been generated by the router
        $handler->add(new AppUrlRewriterMiddleware($this));
        
        $requestUri = $request->getUri();
        $baseUrl = StringDataType::substringBefore($requestUri->getPath(), '/' . $this->buildUrlToFacade(true), '');
        $baseUrl = $requestUri->getScheme() . '://' . $requestUri->getAuthority() . $baseUrl;
        
        $baseRewriteRules = $this->getWorkbench()->getConfig()->getOption('FACADES.DOCSFACADE.BASE_URL_REWRITE');
        if (! $baseRewriteRules->isEmpty()) {
            foreach ($baseRewriteRules->getPropertiesAll() as $pattern => $replace) {
                $baseUrl = preg_replace($pattern, $replace, $baseUrl);
            }
        }
        
        // Add router middleware
        $matcher = function(UriInterface $uri) {
            $path = $uri->getPath();
            $url = StringDataType::substringAfter($path, '/' . $this->buildUrlToFacade(true), '');
            $url = ltrim($url, "/");
            $url = urldecode($url);
            if ($q = $uri->getQuery()) {
                $url .= '?' . $q;
            }
            return $url;
        };
        
        $reader = new MarkdownDocsReader($this->getWorkbench());
        
        switch (true) {
            // If a printout is requested, include all child pages as chapters
            // TODO move the whole printing logic to a middleware
            case ($request->getQueryParams()[self::URL_PARAM_RENDER] === self::URL_PARAM_RENDER_PRINT):
                $templatePath = Filemanager::pathJoin([$this->getApp()->getDirectoryAbsolutePath(), 'Facades/DocsFacade/templatePDF.html']);
                $template = new PlaceholderFileTemplate($templatePath, $baseUrl . '/' . $this->buildUrlToFacade(true));
                $handler->add(new FileRouteMiddleware($matcher, $this->getWorkbench()->filemanager()->getPathToVendorFolder(), $reader, $template));       
                $response = $handler->handle($request);

                $htmlString = $response->getBody()->__toString();
                $htmlString = $this->printCombinedPages($htmlString, $requestUri->__toString());

                $response = new Response(200, [], $htmlString);
                $response = $response->withHeader('Content-Type', 'text/html');
                break;
                
            // If the page ist to be rendered as a chapter, used a different template
            // TODO move the whole printing logic to a middleware
            case ($request->getQueryParams()[self::URL_PARAM_RENDER] === self::URL_PARAM_RENDER_CHAPTER):
                $templatePath = Filemanager::pathJoin([$this->getApp()->getDirectoryAbsolutePath(), 'Facades/DocsFacade/templatePDF.html']);
                $template = new PlaceholderFileTemplate($templatePath, $baseUrl . '/' . $this->buildUrlToFacade(true));
                $template->setBreadcrumbsRootName('Documentation');
                $handler->add(new FileRouteMiddleware($matcher, $this->getWorkbench()->filemanager()->getPathToVendorFolder(), $reader, $template));
                $response = $handler->handle($request);
                break;
            
            // By defualt, render the regular interactiv template
            default:
                $templatePath = Filemanager::pathJoin([$this->getApp()->getDirectoryAbsolutePath(), 'Facades/DocsFacade/template.html']);
                $template = new PlaceholderFileTemplate($templatePath, $baseUrl . '/' . $this->buildUrlToFacade(true));
                $template->setBreadcrumbsRootName('Documentation');
                $handler->add(new FileRouteMiddleware($matcher, $this->getWorkbench()->filemanager()->getPathToVendorFolder(), $reader, $template));
                $response = $handler->handle($request);
                break;
        }
        
        foreach ($this->buildHeadersCommon() as $header => $val) {
            $response = $response->withHeader($header, $val);
        }
        return $response;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::buildHeadersCommon()
     */
    protected function buildHeadersCommon() : array
    {
        $facadeHeaders = array_filter($this->getConfig()->getOption('FACADES.DOCSFACADE.HEADERS.COMMON')->toArray());
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
        return 'api/docs';
    }

    protected function printCombinedPages(string $htmlString, string $requestUri) : string
    {
        // Find all links in first document page
        $linksArray = $this->findLinksInHtml($htmlString);
        // Create temp file for saving entire html content for PDF
        $tempFilePath = tempnam(sys_get_temp_dir(), 'combined_content_');
        
        $this->printLinkedPages($tempFilePath, $linksArray);
        $htmlString = $this->addIdToFirstHeading($requestUri, $htmlString);
        $htmlString = $this->replaceHref($htmlString);
        
        // Attach print function to end of html to show print window when accessing the HTML
        // Also add an arrow as a header element to jump back to the first page in the PDF
        $printString =
        '<style>
            @media print {
                body {
                    margin: 2cm; /* Margin for the body content */
                }
            
                /* Custom Header */
                header {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 2cm;
                    text-align: right;
                    font-size: 24px;
                    font-weight: normal;
                    line-height: 2cm;
                    margin-right: 20px
                }
            }
        </style>
            
        <header>' . '<a href="#' . $this->getAnchor($requestUri) . '">↑</a>' . '</header>
            
        <script type="text/javascript">
            window.onload = function() {
                window.print();
            };
        </script>';

        file_put_contents($tempFilePath, $printString, FILE_APPEND | LOCK_EX);
        
        $combinedBodyContent = file_get_contents($tempFilePath);
        // Clean up the temporary file
        unlink($tempFilePath);
        
        // Parse the body content of all links at the end of the body html tag of the first html document page
        $bodyCloseTagPosition = stripos($htmlString, '</body>');
        $htmlString = substr_replace($htmlString, $combinedBodyContent, $bodyCloseTagPosition, 0);
        return $htmlString;
    }
    
    /**
     * Recursivlely add the entire html of all document links to a tempFile
     * @param string $tempFilePath
     * @param array $linksArray
     */
    protected function printLinkedPages(string $tempFilePath, array $linksArray) 
    {
        foreach ($linksArray as $link) {
            // Only process links that are markdown files and have not been processed before
            if (str_ends_with($link, '.md') && !in_array($link, $this->processedLinks)) {
                $this->processedLinks[$this->processedLinksKey] = $link;
                $this->processedLinksKey = $this->processedLinksKey +1;

                $linkRequest = new ServerRequest('GET', $link);
                // Adds queryParam to jump to the right switch case inside of the createResponse function
                $linkRequest = $linkRequest->withQueryParams([self::URL_PARAM_RENDER => self::URL_PARAM_RENDER_CHAPTER]);
                $linkResponse = $this->createResponse($linkRequest);
                // Adds a page break before start of each markdown file
                $pageBreak = '<div style="page-break-before: always;">';
                $htmlString = $pageBreak . $linkResponse->getBody()->__toString();
                $linksArrayRecursive = $this->findLinksInHTML($htmlString);
                
                $htmlString = $this->replaceHref($htmlString);
                $htmlString = $this->addIdToFirstHeading($link, $htmlString);

                // Write the body content to the temporary file
                file_put_contents($tempFilePath, $htmlString, FILE_APPEND | LOCK_EX);
                
                if (!empty($linksArrayRecursive)) {
                    $this->printLinkedPages($tempFilePath, $linksArrayRecursive);
                }
            }
        }
    }
    
    /**
     * Returns bodyContent as string from a html string
     * @param string $html
     * @return string
     */
    protected function getBodyContent(string $html) : string 
    {
        $bodyContent = '';
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            $bodyContent = $matches[1];
        }
        return $bodyContent;
    }
    
    /**
     * Replaces the links to PDF external websites with links that jump to a PDF internal section
     * From < a href="http://.../Section1.md">Section 1</a >
     * To < a href="#391e16dd556a3f7593801553b2f09bef">Section 1</a >
     * @param string $htmlString
     * @return string
     */
    protected function replaceHref(string $htmlString): string 
    {
        /**
         * Define the pattern to capture the entire URL (including the domain and path) inside the href attribute
         * The regex pattern makes sure that a user can add additional attributes like style in their html url definition.
         * E.g.: <a href="http://localhost/path/to/Section1.md" style="text-decoration:none; color:#000;">Section1</a></li>
         */
        $pattern = '/<a\s+[^>]*href="([^"]*\/[^\/]+\.md)(?:\?' . self::URL_PARAM_RENDER . '=' . self::URL_PARAM_RENDER_PRINT . ')?".*?>(.*?)<\/a>/';
        $matches = [];
        preg_match_all($pattern, $htmlString, $matches);

        if (empty($matches[1])) {
            return $htmlString;
        }

        // Define the replacement pattern e.g. <a href="#391e16dd556a3f7593801553b2f09bef">Section 1</a>
        $from = [];
        $to = [];
        foreach ($matches[1] as $i => $url) {
            $from[] = '<a href="' . $url . '"';
            $to[] = '<a href="#' . $this->getAnchor($url) . '"';
        }
        
        return str_replace($from, $to, $htmlString);
    }

    protected function getAnchor(string $url) : string
    {
        return md5($url);
    }
    
    /**
     * Adds the path/url of the markdown file as an id to the first heading of the markdown to be able to reference it with an href element.
     * Sometimes Html-to-PDF converters add a <title> html tag to a document with <h1>-<h6> html tags inside. These headings will be ignored.
     * 
     * @param string $link
     * @param string $htmlString
     * @return string
     */
    protected function addIdToFirstHeading(string $link, string $htmlString): string 
    {   
        $doc = new DOMDocument();
        // Suppress errors due to invalid HTML structure
        libxml_use_internal_errors(true);
        $doc->loadHTML($htmlString);
        libxml_clear_errors();

        // Use XPath to find all headings (h1-h6) outside of <title> tags
        $xpath = new DOMXPath($doc);
        $headers = $xpath->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6][not(ancestor::title)]');

        if ($headers->length > 0) {
            $firstHeader = $headers->item(0);
            // Add the id attribute to the first heading element
            $firstHeader->setAttribute('id', $this->getAnchor($link));
        }

        return $doc->saveHTML();
    }

    
    /**
     * Returns all links inside of a html string as an array
     * @param string $html
     * @return array
     */
    protected function findLinksInHtml(string $html) : array 
    {
        $dom = new DOMDocument();
        // Suppress errors due to malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $links = $dom->getElementsByTagName('a');
        $extractedLinks = [];
        
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href) {
                $extractedLinks[] = $href;
            }
        } 
        return $extractedLinks;
    }
    
}