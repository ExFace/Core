<?php
namespace exface\Core\Facades\DocsFacade\Placeholders;

use exface\Core\CommonLogic\QueryBuilder\RowDataArraySorter;
use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

class SubPageListResolver extends AbstractPlaceholderResolver implements PlaceholderResolverInterface
{
    const OPTION_DEPTH = "depth";

    const OPTION_LIST_TYPE = "list-type";

    const LIST_TYPE_NONE = "none";

    const LIST_TYPE_BULLET = "bullet";

    private $pagePath = null;
    private $optionDefaults = [
        self::OPTION_LIST_TYPE => self::LIST_TYPE_BULLET,
    ];

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
        $names = array_map(fn($ph) => $ph['name'], $placeholders);
        $filteredNames = $this->filterPlaceholders($names);
        foreach ($placeholders as $i => $placeholder) {
            if (in_array($placeholder['name'], $filteredNames)) {
                $options = $placeholder['options'];
                parse_str($options, $optionsArray);
                $rootDirectory = FilePathDataType::findFolderPath($this->pagePath);
                $markdownStructure = $this->generateMarkdownList($rootDirectory);
                $val = $this->renderMarkdownList($markdownStructure, $this->getOption('list-type',$optionsArray), $this->getOption('depth',$optionsArray));
                $vals[$i] = $val;
            }
        }
        return $vals;
    }

    protected function getOption(string $optionName, array $callValues)
    {
        $value = $callValues[$optionName] ?? null;
        $default = $this->optionDefaults[$optionName] ?? null;
        if ($optionName == self::OPTION_LIST_TYPE) {
            // validation
        }
        if($optionName == self::OPTION_DEPTH) {
            $value = (int)$value;
        }
        return $value ?? $default;
    }
    
    protected function generateMarkdownList(string $directory, int $level = 0): array {
        $items = [];
        $files = scandir($directory);
    
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || strtolower($file) === 'index.md') {
                continue;
            }
            
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;
    
            if (is_dir($filePath)) {
                $folderIndex = $filePath . DIRECTORY_SEPARATOR . 'index.md';
                $folderTitle = ucfirst($file); // Default folder name as title
                if (file_exists($folderIndex)) {
                    $folderTitle = MarkdownDataType::findHeadOfFile($folderIndex);
                    $items[] = [
                        'title' => $folderTitle,
                        'link' => $this->relativePath($folderIndex),
                        'is_dir' => true,
                        'children' => $this->generateMarkdownList($filePath, $level++),
                    ];
                } else {
                    if($folderTitle != 'Bilder') {
                        $items[] = [
                            'title' => $folderTitle,
                            'link' => null,
                            'is_dir' => true,
                            'children' => $this->generateMarkdownList($filePath, $level++),
                        ];
                    }
                }
            } elseif (pathinfo($filePath, PATHINFO_EXTENSION) === 'md') {
                $items[] = [
                    'title' => MarkdownDataType::findHeadOfFile($filePath),
                    'link' => $this->relativePath($filePath),
                    'is_dir' => false,
                    'children' => [],
                ];
            }
        }
    
        
        $sorter = new RowDataArraySorter();
        $sorter->addCriteria('title', SORT_ASC);
        return $sorter->sort($items);
    }
    
    function renderMarkdownList($items, $listType, $depth, $level = 0) {
        $output = '';
        $listStyle = '';

        if ($level === 0) {
            $output .= '<div class="list-wrapper">' . "\n";
        }

        if ($level === $depth) {
            return $output;
        }
        $indent = 20 * $level;

        switch ($listType) {
            case self::LIST_TYPE_NONE:
                $listStyle = " style=\"list-style-type: none; padding-left: 0; margin-left: {$indent}px;\"";
                break;
            default:
                $listStyle = '';
                break;
        }

        $output .= "<ul$listStyle>\n";
        foreach ($items as $item) {
            $output .= "<li>";

            if (!empty($item['link'])) {
                $output .= '<a href="' . htmlspecialchars($item['link']) . '">' . htmlspecialchars($item['title']) . '</a>';
            } else {
                $output .= htmlspecialchars($item['title']);
            }

            if (!empty($item['children'])) {
                $output .= $this->renderMarkdownList($item['children'], $listType, $depth, $level + 1);
            }

            $output .= "</li>\n";
        }
        $output .= "</ul>\n";

        if ($level === 0) {
            $output .= '</div>' . "\n";
        }
        return $output;
    }

    protected function relativePath(string $fullPath): string
    {
        $marker = 'Docs';
        $normalizedFull = FilePathDataType::normalize($fullPath);
        $parts = explode("/$marker/", $normalizedFull);

        return isset($parts[1]) ? $parts[1] : null;
    }
}