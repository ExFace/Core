<?php

namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\TemplateRenderer\PlaceholderValueInvalidError;
use exface\Core\Interfaces\AppInterface;
use GuzzleHttp\Psr7\Uri;
use exface\Core\Interfaces\WorkbenchInterface;


class DocMarkdownPrinter
{
    
    private WorkbenchInterface $workbench;
    
    private Uri $uri;
    
    private ?string $filePath = null;
   
    private  ?AppInterface $app = null;
    
    private  ?string $docsPath = null;
    
    public function __construct(WorkbenchInterface $workbench, string $filePath = null)
    {
        $this->workbench = $workbench;
        
        if($filePath){
            $this->uri = new Uri($filePath);
            $this->filePath = $this->normalizePath(rawurldecode($this->uri->getPath()));
            $appAlias = $this->extractApp($this->filePath);
            $this->app = $this->workbench->getApp($appAlias);
            $this->docsPath = $this->extractDocPath($this->filePath);
            
        }
        
    }

    protected function normalizePath(string $path): string
    {
        $path = str_replace(['\/', '\\'], '/', $path);

        $path = preg_replace('#/+#', '/', $path);

        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

        $pattern = '#'.preg_quote(DIRECTORY_SEPARATOR).'+' . '#';
        $path = preg_replace($pattern, DIRECTORY_SEPARATOR, $path);

        return $path;
    }



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


    public function setAppAlias(string $appAlias): DocMarkdownPrinter
    {
        $this->app = $this->workbench->getApp($appAlias);
        return $this;
    }

    public function getApp(): ?AppInterface
    {
        return $this->app;
    }
    
    public function getAppAlias(): ?string
    {
        return $this->app->getAlias();
    }

    public function getDocsPath(): ?string
    {
        return  "Docs" . DIRECTORY_SEPARATOR . $this->docsPath;
    }
    
    public function setDocsPath(string $docsPath): DocMarkdownPrinter
    {
        $this->uri = new Uri($docsPath);
        $docsPath = $this->normalizePath(rawurldecode($this->uri->getPath()));
        $path = $this->extractDocPath($docsPath);
        $this->docsPath = $path !== "" ? $path : $docsPath;
        return $this;
    }
    
    public function getAbsolutePath(): ?string
    {
        return $this->app->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . $this->getDocsPath();
    }
    
    public function getDirectoryPath(): string
    {
        return $this->app->getDirectory();
    }
    
    public function docsExists(): bool
    {
        return file_exists($this->app->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . "Docs");
    }
    
    
    
    


    public function getMarkdown(): string
    {
        $markdown = $this->readFile($this->getAbsolutePath());
        if(StringDataType::endsWith($this->uri->getPath(), 'UXON_prototypes.md')) {
            $query = $this->uri->getQuery();
            $params = [];
            parse_str($query, $params);
            $selector = urldecode($params['selector']);
            $printer = new UxonPrototypeMarkdownPrinter($this->workbench, $selector);
            $markdown = $printer->getMarkdown();
        }
        
        
        
        
        return $this->rebaseRelativeLinks($markdown, $this->getAbsolutePath(), $this->getDirectoryPath(),0);
    }
    
    
    
    
    //functions from LinkRebaser

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
                $output .= $this->getTableOfContents($newContent, $fullPath, dirname($fullPath), $depth - 1, $currentDepth + 1);
            }
        }

        return $output;
    }

    public function rebaseRelativeLinks(string $content, string $filePath, string $basePath, int $depth, int $currentDepth = 2): string
    {

        if ($depth < 0) {
            return "";
        }


        $pattern = '/
            (?P<bang>!)?                              # optional ! fuer Bilder
            \[(?P<text>[^\]]*)\]                      # Linktext
            \(
                \s*(?P<url>[^)\s]+)                   # Ziel ohne Leerzeichen bis Klammer
                (?:\s+"(?P<title>[^"]*)")?            # optionaler Title
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

    protected function isExternalLink(string $link) : bool
    {
        return str_starts_with($link, 'http');
    }

    protected function isKeyboardShortcut(string $text) : bool
    {
        return str_starts_with($text, '<kbd>');
    }

    private function isPureAnchor(string $url): bool
    {
        return $url !== '' && $url[0] === '#';
    }

    private function isDataLike(string $url): bool
    {
        return (bool)preg_match('#^(data:|about:)#i', $url);
    }

    protected function formatLink(string $text, string $link, int $depth) : string
    {
        return str_repeat("#", $depth) . "- " . $text . " (" . $link . ")\n";
    }

    protected function getRelativePath(string $filePath, string $linkedFile, string $basePath) : string
    {
        $normalizedPath = dirname($filePath) . DIRECTORY_SEPARATOR . $linkedFile;
        $fullPath = realpath($normalizedPath);
        $base = 'api'. DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . $basePath . '\\';
        return strstr($fullPath, 'Docs\\') ? $base . strstr($fullPath, 'Docs\\') : $linkedFile;
    }
    
    // from File Reader

    public function readFile(string $filePath): string {
        if (! file_exists($filePath)) {
            return 'ERROR: file not found!';
        }
        $md = file_get_contents($filePath);
        return $md;
    }
    
}