<?php

namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\TemplateRenderer\PlaceholderValueInvalidError;
use exface\Core\Interfaces\AppInterface;
use GuzzleHttp\Psr7\Uri;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * DocMarkdownPrinter loads a markdown file from an app Docs folder
 * and rewrites internal links so they point to the api docs entry points.
 *
 * It can be constructed from an incoming request path or configured later
 * via app alias and docs path. 
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
     * Creates a new printer for the given workbench and optional request path.
     *
     * If a file path is provided it is normalized, the app alias is extracted
     * from the api docs segment and the Docs sub path is derived from it.
     *
     * @param WorkbenchInterface $workbench 
     * @param string|null $filePath Optional incoming request path or url
     */
    public function __construct(WorkbenchInterface $workbench, string $filePath = null)
    {
        $this->workbench = $workbench;

        if ($filePath) {
            $this->uri = new Uri($filePath);
            $this->filePath = $this->normalizePath(rawurldecode($this->uri->getPath()));
            $appAlias = $this->extractApp($this->filePath);
            $this->app = $this->workbench->getApp($appAlias);
            $this->docsPath = $this->extractDocPath($this->filePath);
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
        $markdown = $this->readFile($this->getAbsolutePath());

        return $this->rebaseRelativeLinks(
            $markdown,
            $this->getAbsolutePath(),
            $this->getDirectoryPath(),
            0
        );
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
     * The given value is interpreted as Uri, normalized and then the
     * Docs sub path is extracted. If no Docs segment is found the
     * normalized path is used as is.
     *
     * @param string $docsPath Url or path to a docs file
     * @return DocMarkdownPrinter Fluent interface
     */
    public function setDocsPath(string $docsPath): DocMarkdownPrinter
    {
        $this->uri = new Uri($docsPath);
        $docsPath = $this->normalizePath(rawurldecode($this->uri->getPath()));
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
     * Rewrites relative markdown links so they point to the api docs base path.
     *
     * For each markdown link or image link this method computes a new relative
     * path based on the current file and the app docs base directory.
     * External links, pure anchors and data urls are not changed.
     *
     * The method walks the content once and uses a callback to rebuild each
     * markdown link in place.
     *
     * @param string $content Original markdown content
     * @param string $filePath Path of the current file
     * @param string $basePath App docs base path
     * @param int $depth Unused here but kept for a compatible signature
     * @param int $currentDepth Unused in the rewrite but part of the signature
     * @return string Markdown content with rebased links
     */
    public function rebaseRelativeLinks(string $content, string $filePath, string $basePath, int $depth, int $currentDepth = 2): string
    {
        if ($depth < 0) {
            return "";
        }

        $pattern = '/
            (?P<bang>!)?                              # optional ! for images
            \[(?P<text>[^\]]*)\]                      # link text
            \(
                \s*(?P<url>[^)\s]+)                   # target url
                (?:\s+"(?P<title>[^"]*)")?            # optional title
            \)
        /x';

        $dirOfFile = dirname($filePath);

        $cb = function(array $m) use ($filePath, $basePath, $dirOfFile) {
            $text  = $m['text'];
            $url   = $m['url'];
            $title = isset($m['title']) ? $m['title'] : null;

            if ($this->isKeyboardShortcut($text)) {
                return $m[0];
            }

            if ($this->isExternalLink($url) || $this->isPureAnchor($url) || $this->isDataLike($url)) {
                return $m[0];
            }

            $fragment = '';
            if (strpos($url, '#') !== false) {
                [$url, $frag] = explode('#', $url, 2);
                $fragment = '#'.$frag;
            }

            $rebased = $this->getRelativePath($filePath, $url, $basePath);

            if (!$rebased || $rebased === $url) {
                $candidate = realpath($dirOfFile . DIRECTORY_SEPARATOR . $url);
                if ($candidate !== false) {
                    $base = 'api' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . $basePath . DIRECTORY_SEPARATOR;
                    $pos = strpos($candidate, 'Docs' . DIRECTORY_SEPARATOR);
                    if ($pos !== false) {
                        $rebased = $base . substr($candidate, $pos);
                    } else {
                        $rebased = $url;
                    }
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
        $normalizedPath = dirname($filePath) . DIRECTORY_SEPARATOR . $linkedFile;
        $fullPath = realpath($normalizedPath);
        $base = 'api'. DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . $basePath . '\\';
        return strstr($fullPath, 'Docs\\') ? $base . strstr($fullPath, 'Docs\\') : $linkedFile;
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
            return 'ERROR: file not found!';
        }
        $md = file_get_contents($filePath);
        return $md;
    }

}