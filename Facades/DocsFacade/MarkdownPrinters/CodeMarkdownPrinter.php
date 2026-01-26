<?php

namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\AppInterface;
use GuzzleHttp\Psr7\Uri;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * CodeMarkdownPrinter loads a markdown file from an php file
 *
 * Expected request pattern:
 *   api/docs/Vendor/App/Some/Path/File.php
 *
 * The path part after Vendor and App is used directly as relative file path
 * inside the app directory.
 */
class CodeMarkdownPrinter
{
    private WorkbenchInterface $workbench;

    private ?AppInterface $app = null;

    private ?string $docsPath = null;

    public function __construct(WorkbenchInterface $workbench, string $filePath = null)
    {
        $this->workbench = $workbench;

        if ($filePath) {

            $normalizedPath = $this->normalizePath(rawurldecode($filePath));

            [$appAlias, $relativePath] = $this->extractAppAndRelativePath($normalizedPath);

            if ($appAlias !== '') {
                $this->app = $this->workbench->getApp($appAlias);
            }

            if ($relativePath !== '') {
                $this->docsPath = $relativePath;
            }
        }
    }

    /**
     * Reads the markdown document and returns its content.
     *     *
     * @return string Markdown content
     */
    public function getMarkdown(): string
    {
        $path = $this->getAbsolutePath();
        if(!$path){
            return 'ERROR: file not found!';
        }
        if(!StringDataType::endsWith($path, '.php')) {
            return "ERROR: This path does not refer to a PHP file. Therefore, the file could not be read.";
        }
        
        return $this->rebasePaths($this->readFile($path));
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
     * Extracts app alias and relative path from an api docs path.
     *
     * Expected pattern:
     *   api/docs/Vendor/App/some/path/file.php
     * becomes:
     *   alias: "vendor.app"
     *   relativePath: "some/path/file.php"
     *
     * @param string $link Normalized path
     * @return array{0:string,1:string} [appAlias, relativePath]
     */
    protected function extractAppAndRelativePath(string $link): array
    {
        $link = $this->normalizePath($link);

        $ds = preg_quote(DIRECTORY_SEPARATOR, '#');
        $pattern = "#api{$ds}docs{$ds}([^{$ds}]+){$ds}([^{$ds}]+){$ds}(.+)$#i";

        if (preg_match($pattern, $link, $m)) {
            $appAlias = strtolower($m[1] . "." . $m[2]);
            $relativePath = $m[3];
            return [$appAlias, $relativePath];
        }

        return ['', ''];
    }

    /**
     * Returns the absolute file system path to the current markdown file.
     *
     * @return string Absolute path to the markdown file
     */
    public function getAbsolutePath(): ?string
    {
        if (! $this->app || ! $this->docsPath) {
            return null;
        }

        return $this->app->getDirectoryAbsolutePath()
            . DIRECTORY_SEPARATOR
            . $this->docsPath;
    }

   

    /**
     * Reads the content of a markdown file from disk.
     *
     * If the file does not exist an error message is returned instead.
     *
     * @param string $filePath Absolute path to the markdown file
     * @return string File content or error message
     */
    protected function readFile(string $filePath): string
    {
        if (! file_exists($filePath)) {
            return 'ERROR: file not found!';
        }

        $md = file_get_contents($filePath);
        return $md === false ? '' : $md;
    }

    /**
     * Rewrites specific PHP use statements by inserting the api docs prefix.
     *
     * The raw input contains PHP code as a string. All matching use statements
     * beginning with "use exface\" are rewritten so they begin with
     * "use api\docs\exface\" instead. Other lines remain unchanged.
     */
    protected function rebasePaths(string $raw) : string
    {
        return preg_replace(
            '/^use\s+exface\\\\(.*);/m',
            'use api\\docs\\exface\\\\$1;',
            $raw
        );
    }





}