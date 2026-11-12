<?php
namespace exface\Core\Facades\DocsFacade\Middleware;

use exface\Core\DataTypes\UrlDataType;
use exface\Core\Exceptions\Facades\HttpBadRequestError;
use exface\Core\Exceptions\RuntimeException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Generates component documentation using the installed component registry.
 */
class ComponentPrinterMiddleware extends AbstractMarkdownPrinterMiddleware
{
    /**
     * Leaves the static usage page to the file router when no component is requested.
     *
     * {@inheritDoc}
     * @see AbstractMarkdownPrinterMiddleware::isRequestToPrinterFile()
     */
    protected function isRequestToPrinterFile(ServerRequestInterface $request): bool
    {
        if (! parent::isRequestToPrinterFile($request)) {
            return false;
        }
        $params = UrlDataType::findUrlParams($request->getUri());
        return array_key_exists('component', $params) || array_key_exists('selector', $params);
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\MarkdownPrinterMiddlewareInterface::getMarkdown()
     */
    public function getMarkdown(ServerRequestInterface $request): string
    {
        $params = UrlDataType::findUrlParams($request->getUri());
        foreach (['component', 'selector'] as $name) {
            if (! is_string($params[$name] ?? null) || trim($params[$name]) === '') {
                throw new HttpBadRequestError($request, 'Expected a non-empty "' . $name . '" URL parameter for component documentation');
            }
        }

        $component = trim(mb_strtolower($params['component']));
        $selector = $params['selector'];
        $markdown = $this->getWorkbench()->getComponentRegistry()->getDocsForSelector($component, $selector);
        if ($markdown === null) {
            throw new RuntimeException('No documentation found for component ' . $component . ' with selector ' . $selector);
        }
        return $markdown;
    }
}
