<?php

namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\Interfaces\AppInterface;
use GuzzleHttp\Psr7\Uri;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * DocMarkdownPrinter loads a markdown file from an app Docs folder
 * and rewrites internal links so they point to the api docs entry points.
 *
 * It can be constructed from an incoming request path or configured later
 * via app alias and docs path. 
 *
 * TODO: Support relative links that go up one or more directories, such as
 * `../file.md`. Going back in the path does not work yet.
 */
class DocMarkdownPrinter
{
    private WorkbenchInterface $workbench;

    /**
     * Original Uri that was used to construct this printer.
     */
    private Uri $uri;

    /**
     * Normalized file path from the incoming request or constructor.
     */
    private ?string $filePath = null;

    private ?AppInterface $app = null;

    /**
     * Path to the document inside the Docs folder of the app.
     */
    private ?string $docsPath = null;
    /**
     * Maximum depth for inlining linked markdown documents.
     */
    private int $depth = 0;

    /**
     * Creates a new printer for the given workbench and optional request path.
     *
     * If a file path is provided it is normalized, the app alias is extracted
     * from the api docs segment and the Docs sub path is derived from it.
     *
     * All additional constructor options are optional. Existing constructor
     * calls with only workbench and file path remain compatible.
     *
     * @param WorkbenchInterface $workbench
     * @param string|null $filePath Optional incoming request path or url
     * @param int|null $depth Optional maximum recursion depth for markdown inlining
     * @param string|null $appAlias Optional app alias override
     * @param string|null $docsPath Optional docs path override
     */
    public function __construct(
        WorkbenchInterface $workbench,
        string $filePath = null,
        ?int $depth = null,
        ?string $appAlias = null,
        ?string $docsPath = null
    )
    {
        $this->workbench = $workbench;

        if ($filePath) {
            $this->uri = new Uri($filePath);
            $this->filePath = $this->normalizePath(rawurldecode($this->uri->getPath()));
            $appAlias = $this->extractApp($this->filePath);
            $this->app = $this->workbench->getApp($appAlias);
            $this->docsPath = $this->extractDocPath($this->filePath);
        }

        if ($appAlias !== null && $appAlias !== '') {
            $this->setAppAlias($appAlias);
        }

        if ($docsPath !== null && $docsPath !== '') {
            $this->setDocsPath($docsPath);
        }

        if ($depth !== null) {
            $this->setDepth($depth);
        }
    }

    /**
     * Reads the markdown document and returns its content
     * with rebased relative links for the api docs context.
     *
     * @return string Rewritten markdown content
     */
    public function getMarkdown(): string
    {
        $absolutePath = $this->getAbsolutePath();
        $markdown = $this->readFile($absolutePath);

        return $this->rebaseRelativeLinks(
            $markdown,
            $absolutePath,
            $this->getDirectoryPath(),
            $this->getDepth()
        );
    }

    public function getErrorMessage(): string
    {
        return 'ERROR: file not found!';
    }

    /**
     * Sets the maximum depth for inlining linked markdown documents.
     *
     * A depth of 0 keeps the default behavior and only rewrites links.
     * Higher values replace links to local markdown files with the content
     * of those files, recursively up to the configured depth.
     *
     * @param int $depth Maximum link recursion depth
     * @return DocMarkdownPrinter Fluent interface
     */
    public function setDepth(int $depth): DocMarkdownPrinter
    {
        $this->depth = max(0, $depth);
        return $this;
    }

    /**
     * Returns the maximum depth for inlining linked markdown documents.
     *
     * @return int Configured maximum recursion depth
     */
    public function getDepth(): int
    {
        return $this->depth;
    }

    /**
     * Normalizes a file path to use a consistent directory separator.
     *
     * All slash variants are converted to the system directory separator
     * and duplicate separators are collapsed.
     *
     * @param string $path Raw path
     * @return string Normalized path
     */
    protected function normalizePath(string $path): string
    {
        $path = str_replace(['\/', '\\'], '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

        $pattern = '#'.preg_quote(DIRECTORY_SEPARATOR).'+' . '#';
        $path = preg_replace($pattern, DIRECTORY_SEPARATOR, $path);

        return $path;
    }

    /**
     * Extracts the app alias from an api docs path.
     *
     * Expected pattern:
     *   api/docs/exface/Core/Docs/...
     * which is converted to lower case "exface.core".
     *
     * @param string $link Normalized path
     * @return string Extracted app alias or empty string if none could be found
     */
    protected function extractApp(string $link): string
    {
        $link = $this->normalizePath($link);

        $ds = preg_quote(DIRECTORY_SEPARATOR, '#');
        $pattern = "#api{$ds}docs{$ds}([^{$ds}]+){$ds}([^{$ds}]+){$ds}#i";

        if (preg_match($pattern, $link, $m)) {
            return strtolower($m[1] . "." . $m[2]);
        }

        return "";
    }

    /**
     * Extracts the relative document path inside the Docs folder.
     *
     * Example:
     *   .../Docs/Section/file.md  →  Section/file.md
     *
     * @param string $link Normalized path
     * @return string Relative Docs path or empty string
     */
    protected function extractDocPath(string $link): string
    {
        $link = $this->normalizePath($link);

        $ds = preg_quote(DIRECTORY_SEPARATOR, '#');
        $pattern = "#Docs{$ds}(.+)$#";

        if (preg_match($pattern, $link, $m)) {
            return $m[1];
        }

        return "";
    }

    /**
     * Sets the app alias manually and resolves the app instance
     * from the workbench.
     *
     * @param string $appAlias App alias to use
     * @return DocMarkdownPrinter Fluent interface
     */
    public function setAppAlias(string $appAlias): DocMarkdownPrinter
    {
        $this->app = $this->workbench->getApp($appAlias);
        return $this;
    }

    /**
     * Returns the resolved app or null if none is set.
     *
     * @return AppInterface|null Current app instance
     */
    public function getApp(): ?AppInterface
    {
        return $this->app;
    }

    /**
     * Returns the alias of the current app.
     *
     * @return string|null App alias or null if no app is set
     */
    public function getAppAlias(): ?string
    {
        return $this->app->getAlias();
    }

    /**
     * Returns the Docs relative path prefixed with the Docs folder name.
     *
     * @return string|null Docs path including "Docs" prefix
     */
    public function getDocsPath(): ?string
    {
        return "Docs" . DIRECTORY_SEPARATOR . $this->docsPath;
    }

    /**
     * Sets the Docs path from a url or path string.
     *
        * If a Docs segment exists, only the path inside that folder is kept.
        * Otherwise the normalized value is used as Docs-relative path.
     *
     * @param string $docsPath Url or path to a docs file
     * @return DocMarkdownPrinter Fluent interface
     */
    public function setDocsPath(string $docsPath): DocMarkdownPrinter
    {
        $docsPath = $this->normalizePath(rawurldecode($docsPath));
        $path = $this->extractDocPath($docsPath);
        $this->docsPath = $path !== "" ? $path : $docsPath;
        return $this;
    }

    /**
     * Returns the absolute file system path to the current Docs file.
     *
     * @return string|null Absolute path to the markdown file
     */
    public function getAbsolutePath(): ?string
    {
        return $this->app->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . $this->getDocsPath();
    }

    /**
     * Returns the base directory of the current app.
     *
     * @return string App directory path
     */
    public function getDirectoryPath(): string
    {
        return $this->app->getDirectory();
    }

    /**
     * Checks whether a Docs folder exists in the app directory.
     *
     * @return bool True if the Docs folder exists
     */
    public function docsExists(): bool
    {
        return file_exists($this->app->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . "Docs");
    }

    /**
     * Builds a table of contents from a markdown document.
     *
     * The method parses all markdown links, emits formatted entries and,
     * for each linked markdown file, loads it and calls itself recursively
     * until the given depth is reached. Already processed links are skipped
     * to avoid infinite recursion.
     *
     * @param string $content Markdown content to scan
     * @param string $filePath Path to the current file
     * @param string $basePath Base app docs path used in api docs links
     * @param int $depth Maximum recursion depth
     * @param int $currentDepth Current heading depth for nested entries
     * @return string Markdown list that represents the table of contents
     */
    public function getTableOfContents(string $content, string $filePath, string $basePath, int $depth, int $currentDepth = 2): string
    {
        if ($depth < 0) {
            return "";
        }

        $pattern = '/\[(.*?)\]\((.*?)\)/';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
        $output = "";

        foreach ($matches as $match) {
            $linkedFile = $match[2];

            if ($this->isExternalLink($linkedFile)) {
                $output .= $this->formatLink($match[1], $linkedFile, $currentDepth);
                continue;
            }

            if ($this->isKeyboardShortcut($match[1])) {
                continue;
            }

            $relativePath = $this->getRelativePath($filePath, $linkedFile, $basePath);

            if (isset($this->processedLinks[$relativePath])) {
                continue;
            }

            $this->processedLinks[$relativePath] = true;
            $output .= $this->formatLink($match[1], $relativePath, $currentDepth);

            $fullPath = realpath(dirname($filePath) . DIRECTORY_SEPARATOR . $linkedFile);

            if ($fullPath && pathinfo($fullPath, PATHINFO_EXTENSION) === 'md') {
                $newContent = $this->readFile($fullPath);
                $output .= $this->getTableOfContents(
                    $newContent,
                    $fullPath,
                    dirname($fullPath),
                    $depth - 1,
                    $currentDepth + 1
                );
            }
        }

        return $output;
    }

    /**
     * Rewrites relative markdown and HTML links so they point to the api docs base path.
     *
     * Markdown links and image links are handled as before:
     *   [Text](file.md)
     *   ![Alt](image.png)
     *
     * HTML href/src attributes are now handled with the same rebasing logic:
     *   <a href="file.md">
     *   <img src="image.png">
     *
     * External links, pure anchors and data/about urls are not changed.
     * If a recursion depth is configured, markdown links to local markdown files
     * can still be replaced with the rendered markdown content of the linked document.
     *
     * @param string $content Original markdown/html content
     * @param string $filePath Path of the current file
     * @param string $basePath App docs base path
     * @param int $depth Remaining recursion depth for inlining markdown links
     * @param int $currentDepth Unused in the rewrite but part of the signature
     * @return string Markdown/html content with rebased links
     */
    public function rebaseRelativeLinks(string $content, string $filePath, string $basePath, int $depth, int $currentDepth = 2): string
    {
        if ($depth < 0) {
            return "";
        }

        $content = $this->rebaseMarkdownLinks($content, $filePath, $basePath, $depth);
        return $this->rebaseHtmlLinkAttributes($content, $filePath, $basePath);
    }

    /**
     * Rewrites markdown links and images.
     *
     * @param string $content Original content
     * @param string $filePath Path of the current file
     * @param string $basePath App docs base path
     * @param int $depth Remaining recursion depth for inlining markdown links
     * @return string Content with rebased markdown links
     */
    protected function rebaseMarkdownLinks(string $content, string $filePath, string $basePath, int $depth): string
    {
        $pattern = '/
            (?P<bang>!)?                              # optional ! for images
            \[(?P<text>[^\]]*)\]                      # link text
            \(
                \s*(?P<url>[^)\s]+)                   # target url
                (?:\s+"(?P<title>[^"]*)")?            # optional title
            \)
        /x';

        $cb = function (array $m) use ($depth, $filePath, $basePath) {
            $text = $m['text'];
            $url = html_entity_decode($m['url'], ENT_QUOTES | ENT_HTML5);
            $title = isset($m['title']) ? $m['title'] : null;

            if ($this->isKeyboardShortcut($text) || $this->shouldKeepLinkUnchanged($url)) {
                return $m[0];
            }

            [$urlWithoutFragment, $fragment] = $this->splitFragment($url);
            $rebased = $this->rebaseLinkTarget($filePath, $urlWithoutFragment, $basePath);

            if ($depth >= 1 && $this->isLocalMarkdownLink($urlWithoutFragment)) {
                $printer = new DocMarkdownPrinter($this->workbench);
                $printer->setDepth($depth - 1);
                $printer->setDocsPath($urlWithoutFragment);
                $appAlias = $this->app->getAliasWithNamespace();
                $printer->setAppAlias($appAlias);
                $md = $printer->getMarkdown();

                if ($md !== $printer->getErrorMessage()) {
                    $rebased = "\n " . $md;
                    $fragment = "";
                }
            }

            $rebased = str_replace('\\', '/', $rebased);
            $titlePart = $title !== null && $title !== '' ? ' "'.$title.'"' : '';
            $bang = !empty($m['bang']) ? '!' : '';

            return $bang . '['.$text.']('.$rebased.$fragment.$titlePart.')';
        };

        return preg_replace_callback($pattern, $cb, $content);
    }

    /**
     * Rewrites href and src attributes in embedded HTML with the same
     * internal-link rebasing logic used for markdown links.
     *
     * @param string $content Original content
     * @param string $filePath Path of the current file
     * @param string $basePath App docs base path
     * @return string Content with rebased html href/src attributes
     */
    protected function rebaseHtmlLinkAttributes(string $content, string $filePath, string $basePath): string
    {
        $pattern = '/(?P<attr>\b(?:href|src)\s*=\s*)(?P<quote>["\'])(?P<url>.*?)(?P=quote)/i';

        $cb = function (array $m) use ($filePath, $basePath) {
            $rawUrl = html_entity_decode($m['url'], ENT_QUOTES | ENT_HTML5);

            if ($this->shouldKeepLinkUnchanged($rawUrl)) {
                return $m[0];
            }

            [$urlWithoutFragment, $fragment] = $this->splitFragment($rawUrl);
            $rebased = $this->rebaseLinkTarget($filePath, $urlWithoutFragment, $basePath);
            $rebased = str_replace('\\', '/', $rebased) . $fragment;

            return $m['attr'] . $m['quote'] . htmlspecialchars($rebased, ENT_QUOTES | ENT_HTML5) . $m['quote'];
        };

        return preg_replace_callback($pattern, $cb, $content);
    }

    /**
     * Rebases one internal link target into the api/docs path.
     *
     * @param string $filePath Path of the current file
     * @param string $linkedFile Linked relative file path
     * @param string $basePath App docs base path
     * @return string Rebases link path or original path if it cannot be resolved
     */
    protected function rebaseLinkTarget(string $filePath, string $linkedFile, string $basePath): string
    {
        if ($linkedFile === '') {
            return $linkedFile;
        }

        $rebased = $this->getRelativePath($filePath, $linkedFile, $basePath);
        return $rebased !== '' ? $rebased : $linkedFile;
    }

    /**
     * Checks whether a link target should not be rewritten.
     *
     * @param string $url Url or path
     * @return bool True if the link must stay unchanged
     */
    protected function shouldKeepLinkUnchanged(string $url): bool
    {
        return $this->isExternalLink($url)
            || $this->isApiDocsLink($url)
            || $this->isPureAnchor($url)
            || $this->isDataLike($url)
            || $this->isMailOrPhoneLink($url);
    }

    /**
     * Splits a url into path and fragment.
     *
     * @param string $url Url or path
     * @return array{0:string,1:string} Path and fragment including leading hash
     */
    protected function splitFragment(string $url): array
    {
        if (strpos($url, '#') === false) {
            return [$url, ''];
        }

        [$path, $fragment] = explode('#', $url, 2);
        return [$path, '#' . $fragment];
    }

    /**
     * Checks whether the local link points to a markdown file.
     *
     * @param string $url Path without fragment
     * @return bool True if it is a markdown file path
     */
    protected function isLocalMarkdownLink(string $url): bool
    {
        return strtolower(pathinfo($url, PATHINFO_EXTENSION)) === 'md';
    }

    /**
     * Checks whether a link is external and should not be rewritten.
     *
     * @param string $link Url or path from markdown
     * @return bool True if the link starts with http
     */
    protected function isExternalLink(string $link) : bool
    {
        return str_starts_with($link, 'http');
    }

    /**
     * Detects markdown link texts that represent keyboard shortcuts.
     *
     * These links are left untouched when rebasing.
     *
     * @param string $text Link text
     * @return bool True if the text starts with a kbd tag
     */
    protected function isKeyboardShortcut(string $text) : bool
    {
        return str_starts_with($text, '<kbd>');
    }

    /**
     * Checks whether the url is a pure anchor without a path.
     *
     * @param string $url Url or fragment
     * @return bool True if it starts with a hash
     */
    private function isPureAnchor(string $url): bool
    {
        return $url !== '' && $url[0] === '#';
    }

    /**
     * Checks whether the url is a data or about url that should not be changed.
     *
     * @param string $url Url string
     * @return bool True if it looks like a data or about url
     */
    private function isDataLike(string $url): bool
    {
        return (bool)preg_match('#^(data:|about:)#i', $url);
    }

    /**
     * Checks whether the url is a mail or phone link that should not be changed.
     *
     * @param string $url Url string
     * @return bool True if it is a mailto or tel link
     */
    private function isMailOrPhoneLink(string $url): bool
    {
        return (bool)preg_match('#^(mailto:|tel:)#i', $url);
    }

    /**
     * Checks whether the url already points to the api docs entry point.
     *
     * @param string $url Url string
     * @return bool True if the url already starts with api/docs
     */
    private function isApiDocsLink(string $url): bool
    {
        $normalized = str_replace('\\', '/', $url);
        return str_starts_with(ltrim($normalized, '/'), 'api/docs/');
    }

    /**
     * Formats a link as a markdown heading entry for the table of contents.
     *
     * @param string $text Link text
     * @param string $link Rebases link path
     * @param int $depth Heading depth used as number of hash characters
     * @return string Formatted markdown line
     */
    protected function formatLink(string $text, string $link, int $depth) : string
    {
        return str_repeat("#", $depth) . "- " . $text . " (" . $link . ")\n";
    }

    /**
     * Computes a relative path for a linked file inside the api docs tree.
     *
     * The method resolves the real path of the target file and then
     * rebuilds a virtual path that starts at api/docs and points
     * into the Docs folder of the app.
     *
     * @param string $filePath Path to the current markdown file
     * @param string $linkedFile Linked relative file path
     * @param string $basePath App docs base path
     * @return string Rebases link path or the original linked file if Docs could not be found
     */
    protected function getRelativePath(string $filePath, string $linkedFile, string $basePath) : string
    {
        if ($linkedFile === '') {
            return $linkedFile;
        }

        $linkedFile = rawurldecode($linkedFile);
        $linkedFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $linkedFile);
        $linkedFile = preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '+#', DIRECTORY_SEPARATOR, $linkedFile);

        if ($this->isApiDocsLink($linkedFile)) {
            return $linkedFile;
        }

        $docsPrefix = 'Docs' . DIRECTORY_SEPARATOR;
        $docsPosition = strpos($linkedFile, $docsPrefix);

        if ($docsPosition !== false) {
            $docsPath = substr($linkedFile, $docsPosition);
        } else {
            $docsPath = $docsPrefix . ltrim($linkedFile, DIRECTORY_SEPARATOR);
        }

        $base = 'api' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . trim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return $base . $docsPath;
    }

    /**
     * Reads the content of a markdown file from disk.
     *
     * If the file does not exist an error message is returned instead.
     *
     * @param string $filePath Absolute path to the markdown file
     * @return string File content or error message
     */
    public function readFile(string $filePath): string
    {
        if (! file_exists($filePath)) {
            return $this->getErrorMessage();
        }
        $md = file_get_contents($filePath);
        return $md;
    }

}