<?php
namespace exface\Core\Facades\DocsFacade\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractMarkdownPlaceholderResolver;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * 
 */
class SubPageListResolver extends AbstractMarkdownPlaceholderResolver implements PlaceholderResolverInterface
{
    const OPTION_DEPTH = "depth";
    const OPTION_ROOT = 'root';
    const OPTION_LIST_TYPE = "list-type";
    const OPTION_LIST_TYPE_NONE = "none";
    const OPTION_LIST_TYPE_BULLET = "bullet";

    private string $pagePath;
    private string $vendorPath;
    private array $optionDefaults = [
        self::OPTION_LIST_TYPE => self::OPTION_LIST_TYPE_BULLET,
    ];

    public function __construct(string $pagePathAbsolute, string $vendorPath, string $prefix = 'SubPageList:') {
        $this->pagePath = $pagePathAbsolute;
        $this->vendorPath = $vendorPath;
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
                if (null !== $rootDirectory = $optionsArray[self::OPTION_ROOT]) {
                    if (! FilePathDataType::isAbsolute($rootDirectory)) {
                        $rootDirectory = $this->vendorPath . DIRECTORY_SEPARATOR . FilePathDataType::normalize($rootDirectory, DIRECTORY_SEPARATOR);
                    }
                } else {
                    $rootDirectory = FilePathDataType::findFolderPath($this->pagePath);
                }
                $depth = $this->getOption(self::OPTION_DEPTH, $optionsArray);
                $listType = $this->getOption(self::OPTION_LIST_TYPE, $optionsArray);
                $markdownStructure = $this->generateMarkdownList($rootDirectory, $depth);
                if ($listType === self::OPTION_LIST_TYPE_NONE) {
                    $val = $this->renderHtmlList($markdownStructure, $listType, $depth);
                } else {
                    $val = $this->renderMarkdownList($markdownStructure, $listType, $depth);
                }
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
    
    protected function generateMarkdownList(string $directory, int $maxDepth) : array 
    {
        $rawList = $this->scanMarkdownDirectory($directory, $maxDepth);
    
        $convert = function(array $items) use (&$convert): array {
            $result = [];
    
            foreach ($items as $item) {
                if ($item['type'] === 'file') {
                    $result[] = [
                        'title' => $item['title'],
                        'link' => $item['link'],
                        'is_dir' => false,
                        'children' => [],
                    ];
                } elseif ($item['type'] === 'directory') {
                    $result[] = [
                        'title' => $item['title'],
                        'link' => $item['link'],
                        'is_dir' => true,
                        'children' => $convert($item['children']),
                    ];
                }
            }
            
            return $result;
        };
    
        return $convert($rawList);
    }
    
    
    function renderMarkdownList($items, $listType, $depth, $level = 0) 
    {
        $output = '';
        if ($level === $depth) {
            return $output;
        }
        $indent = $level + 1;

        switch ($listType) {
            case self::OPTION_LIST_TYPE_BULLET:
                $symbol = '- ';
                break;
            default:
                $symbol = '- ';
                break;
        }

        foreach ($items as $item) {
            $output .= str_pad($symbol, $indent * 2 + strlen($symbol), ' ', STR_PAD_LEFT);

            if (!empty($item['link'])) {
                $output .= '[' . MarkdownDataType::escapeString($item['title']) . '](' . $item['link'] . ')';
            } else {
                $output .= MarkdownDataType::escapeString($item['title']);
            }
            
            $output .= "\n";

            if (!empty($item['children'])) {
                $output .= $this->renderMarkdownList($item['children'], $listType, $depth, ($level + 1));
            }
        }

        if ($level === 0) {
            $output .= "\n";
        }
        return $output;
    }

    function renderHtmlList($items, $listType, $depth, $level = 0) {
        $output = '';

        if ($level === 0) {
            $output .= '<div class="list-wrapper">' . "\n";
        }

        if ($level === $depth) {
            return $output;
        }
        $indent = 20 * $level;

        switch ($listType) {
            case self::OPTION_LIST_TYPE_NONE:
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
                $output .= $this->renderHtmlList($item['children'], $listType, $depth, $level + 1);
            }

            $output .= "</li>\n";
        }
        $output .= "</ul>\n";

        if ($level === 0) {
            $output .= '</div>' . "\n";
        }
        return $output;
    }
}