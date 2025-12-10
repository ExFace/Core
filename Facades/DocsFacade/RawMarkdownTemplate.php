<?php
namespace exface\Core\Facades\DocsFacade;

use kabachello\FileRoute\Interfaces\ContentInterface;
use kabachello\FileRoute\Interfaces\TemplateInterface;

class RawMarkdownTemplate implements TemplateInterface
{
    private $baseUrl = null;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function render(ContentInterface $content): string
    {
        $markdown = $content->getContent();
        // TODO rebase relative links?
        return $markdown;
    }

    /**
     *
     * @param string $html
     * @return mixed
     */
    protected function rebaseRelativeLinks(string $html, string $baseUrl): string
    {
        $base = rtrim($baseUrl, "/\\") . '/';
        $html = preg_replace('#(href|src)="([^:"]*)("|(?:(?:%20|\s|\+)[^"]*"))#', '$1="' . $base . '$2$3', $html);
        return $html;
    }
}