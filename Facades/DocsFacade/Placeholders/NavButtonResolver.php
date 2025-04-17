<?php
namespace exface\Core\Facades\DocsFacade\Placeholders;

use exface\Core\CommonLogic\QueryBuilder\RowDataArraySorter;
use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Exceptions\FileNotFoundError;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

class NavButtonResolver extends AbstractPlaceholderResolver implements PlaceholderResolverInterface
{
    private $pagePath = null;

    public function __construct(string $pagePathAbsolute, string $prefix = 'NavButtons:') {
        $this->pagePath = $pagePathAbsolute;
        $this->setPrefix($prefix);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {
        $vals = [];
        foreach ($this->filterPlaceholders($placeholders) as $placeholder) {
            $rootDirectory = FilePathDataType::findFolderPath($this->pagePath);
            $fileList = $this->getSiblings($rootDirectory);
            $val = $this->createButtons($fileList);
            $vals[$placeholder] = $val;
        }
        return $vals;
    }

    
    protected function getSiblings(string $directory): array {
        if (strtolower(basename($this->pagePath)) === 'index.md') {
            return $this->getIndexSiblings($directory);
        } else {
            return $this->getRegularSiblings($directory);
        }
    }

    protected function getIndexSiblings(string $directory): array {
        $parent = dirname($directory);
        $subfolders = glob($parent . '/*', GLOB_ONLYDIR);
    
        $siblings = [];
        foreach ($subfolders as $folder) {
            $indexPath = $folder . DIRECTORY_SEPARATOR . 'index.md';
            if (file_exists($indexPath)) {
                $siblings[] = [
                    'title' => MarkdownDataType::findHeadOfFile($indexPath),
                    'link' => FilePathDataType::normalize($indexPath)
                ];
            }
        }
    
        $sorter = new RowDataArraySorter();
        $sorter->addCriteria('title', SORT_ASC);
        return $sorter->sort($siblings);
    }
    
    protected function getRegularSiblings(string $directory): array {
        $siblings = [];
        $files = glob($directory . '/*.md');
    
        foreach ($files as $filePath) {
            if (strtolower(basename($filePath)) === 'index.md') {
                continue;
            }
    
            $siblings[] = [
                'title' => MarkdownDataType::findHeadOfFile($filePath),
                'link' => FilePathDataType::normalize($filePath)
            ];
        }
        $sorter = new RowDataArraySorter();
        $sorter->addCriteria('title', SORT_ASC);
        return $sorter->sort($siblings);
    }

    protected function createButtons(array $files): string
    {
        $currentIndex = array_search(FilePathDataType::normalize($this->pagePath), array_column($files, 'link'));
        
        if ($currentIndex === false) {
            "";
        }
        $prev = $currentIndex > 0 ? $files[$currentIndex - 1] : null;
        $next = $currentIndex < count($files) - 1 ? $files[$currentIndex + 1] : null;
        
        $buttons = [];
        if ($prev) $buttons[] = $this->mdButon('Zurück',$this->getRelativePath($this->pagePath, $prev['link']));
        if ($next) $buttons[] = $this->mdButon('Weiter',$this->getRelativePath($this->pagePath, $next['link']));

        $buttonBlock = implode(' ', $buttons) . "\n";
        return $buttonBlock;
    }

    function getRelativePath(string $from, string $to): string {
        $from = is_dir($from) ? rtrim($from, '/') . '/' : dirname($from) . '/';
        $from = FilePathDataType::normalize(realpath($from));
        $to   = FilePathDataType::normalize(realpath($to));
    
        $fromParts = explode('/', trim($from, '/'));
        $toParts   = explode('/', trim($to, '/'));
    
        // remove common roots
        while (count($fromParts) && count($toParts) && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }
    
        return str_repeat('../', count($fromParts)) . implode('/', $toParts);
    }    

    function mdButon(string $buttonText, string $path) {
        return "[<kbd> <br> " . $buttonText . " <br> </kbd>]($path)";
    }

}