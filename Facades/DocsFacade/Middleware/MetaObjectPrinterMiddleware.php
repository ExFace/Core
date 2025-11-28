<?php
namespace exface\Core\Facades\DocsFacade\Middleware;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Facades\DocsFacade\MarkdownContent;
use exface\Core\Facades\DocsFacade\MarkdownPrinters\ObjectMarkdownPrinter;
use exface\Core\Facades\DocsFacade\MarkdownPrinters\UxonPrototypeMarkdownPrinter;
use exface\Core\Interfaces\Facades\MarkdownPrinterMiddlewareInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use GuzzleHttp\Psr7\Response;
use kabachello\FileRoute\Interfaces\FileReaderInterface;
use kabachello\FileRoute\Templates\PlaceholderFileTemplate;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * This middleware generates a Markdown/HTML printout for a metaobject
 * 
 * It will replace the body of a request to a certain URL (provided in the constructor) with generated
 * contents for the object referenced by the `selector` URL parameter.
 * 
 * @author Andrej Kabachnik
 *
 */
class MetaObjectPrinterMiddleware extends AbstractMarkdownPrinterMiddleware
{

    public function __construct(HttpFacadeInterface $facade, string $baseUrl, string $fileUrl, FileReaderInterface $reader, string $objectSelectorUrlParam = 'selector')
    {
        parent::__construct($facade, $baseUrl, $fileUrl, $reader);
        $this->objectSelectorUrlParam = $objectSelectorUrlParam;
    }
    
    public function getMarkdown(ServerRequestInterface $request): string
    {
        $params = $request->getQueryParams();
        $selector = $this->normalize($params[$this->objectSelectorUrlParam]);
        $printer = new ObjectMarkdownPrinter($this->getWorkbench(), $selector);
        return $printer->getMarkdown();
    }

    /**
     * Normalizes a raw link selector by extracting the object ID or alias.
     *
     * The function looks for a pattern like:
     *   objectName [idOrAlias]
     * and returns only the part inside the brackets.
     *
     * Example:
     *   "AI agent" [axenox.GenAI.AI_AGENT]  →  axenox.GenAI.AI_AGENT
     *
     * If the selector does not follow this pattern, the original raw string is returned unchanged.
     */
    protected function normalize(string $raw): string
    {
        $decoded = urldecode($raw);

        $start = strpos($decoded, '[');
        $end   = strpos($decoded, ']');

        if ($start === false || $end === false || $end <= $start) {
            return $raw;
        }

        return substr($decoded, $start + 1, $end - $start - 1);
    }
}