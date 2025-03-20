<?php
namespace exface\Core\Facades\DocsFacade\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

class SubPageListResolver extends AbstractPlaceholderResolver implements PlaceholderResolverInterface
{
    private $pagePath = null;

    public function __construct(string $pagePathAbsolute, string $prefix = 'SubPageList:') {
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
            $options = $this->stripPrefix($placeholder);
            $rootDirectory = FilePathDataType::findFolderPath($this->pagePath);
            $markdownStructure = $this->generateMarkdownList($rootDirectory);
            $val = $this->renderMarkdownList($markdownStructure);
            $vals[$placeholder] = $val;
        }
        return $vals;
    }

    protected function getTopHeading($filePath) {
        $content = file_get_contents($filePath);
        if (preg_match('/^#\s+(.*)$/m', $content, $matches)) {
            return trim($matches[1]);
        }
        return basename($filePath);
    }
    
    protected function generateMarkdownList($directory) {
        $items = [];
        $files = scandir($directory);
    
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
    
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;
    
            if (is_dir($filePath)) {
                $folderIndex = $filePath . DIRECTORY_SEPARATOR . 'index.md';
                $folderTitle = ucfirst($file); // Default folder name as title
                if (file_exists($folderIndex)) {
                    $folderTitle = $this->getTopHeading($folderIndex);
                    $items[] = [
                        'title' => $folderTitle,
                        'link' => $folderIndex,
                        'is_dir' => true,
                        'children' => $this->generateMarkdownList($filePath),
                    ];
                } else {
                    $items[] = [
                        'title' => $folderTitle,
                        'link' => null,
                        'is_dir' => true,
                        'children' => $this->generateMarkdownList($filePath),
                    ];
                }
            } elseif (pathinfo($filePath, PATHINFO_EXTENSION) === 'md') {
                $items[] = [
                    'title' => $this->getTopHeading($filePath),
                    'link' => $filePath,
                    'is_dir' => false,
                    'children' => [],
                ];
            }
        }
    
        usort($items, function ($a, $b) {
            return strcmp(basename($a['link'] ?? $a['title']), basename($b['link'] ?? $b['title']));
        });
    
        return $items;
    }
    
    function renderMarkdownList($items, $level = 0) {
        $output = '';
        $indent = str_repeat('  ', $level);
    
        foreach ($items as $item) {
            $output .= $indent . '- ';
            if ($item['link']) {
                $output .= '[' . $item['title'] . '](' . $item['link'] . ')';
            } else {
                $output .= $item['title'];
            }
            $output .= "\n";
            if (!empty($item['children'])) {
                $output .= $this->renderMarkdownList($item['children'], $level + 1);
            }
        }
    
        return $output;
    }
}