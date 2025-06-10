<?php
namespace exface\Core\Facades\DocsFacade\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractMarkdownPlaceholderResolver;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

class SubPageListResolver extends AbstractMarkdownPlaceholderResolver implements PlaceholderResolverInterface
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
        $this->rootPath = FilePathDataType::normalize(FilePathDataType::findFolderPath($pagePathAbsolute)) . '/';
        $this->setPrefix($prefix);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders, ?LogBookInterface $logbook = null) : array
    {
        $vals = [];                
        $names = array_map(fn($ph) => $ph['name'], $placeholders);
        $filteredNames = $this->filterPlaceholders($names);
        foreach ($placeholders as $i => $placeholder) {
            if (in_array($placeholder['name'], $filteredNames)) {
                $options = $placeholder['options'];
                parse_str($options, $optionsArray);
                $rootDirectory = FilePathDataType::findFolderPath($this->pagePath);
                $markdownStructure = $this->generateMarkdownList($rootDirectory, $this->getOption('depth',$optionsArray));
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
}