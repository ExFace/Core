<?php
namespace exface\Core\Facades\DocsFacade\Placeholders;

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
        foreach ($this->filterPlaceholders($placeholders) as $placeholder) {
            $options = $this->stripPrefix($placeholder);
            parse_str($options, $optionsArray);
            $rootDirectory = FilePathDataType::findFolderPath($this->pagePath);
            $markdownStructure = $this->generateMarkdownList($rootDirectory);
            $val = $this->renderMarkdownList($markdownStructure, $this->getOption('list-type',$optionsArray), $this->getOption('depth',$optionsArray));
            $vals[$placeholder] = $val;
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
                    $items[] = [
                        'title' => $folderTitle,
                        'link' => null,
                        'is_dir' => true,
                        'children' => $this->generateMarkdownList($filePath, $level++),
                    ];
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
    
        return $this->sortItems($items);
    }

    function sortItems(array $items): array
    {
        usort($items, function ($a, $b) {
            $aTitle = $a['title'] ?? '';
            $bTitle = $b['title'] ?? '';
    
            $aIsNumeric = preg_match('/^\d+(\.\d+)*$/', $aTitle);
            $bIsNumeric = preg_match('/^\d+(\.\d+)*$/', $bTitle);
    
            // take number started items first
            if ($aIsNumeric && !$bIsNumeric) return -1;
            if (!$aIsNumeric && $bIsNumeric) return 1;
    
            // if both start with number, devide and compare
            if ($aIsNumeric && $bIsNumeric) {
                $aParts = array_map('intval', explode('.', $aTitle));
                $bParts = array_map('intval', explode('.', $bTitle));
                $maxLen = max(count($aParts), count($bParts));
    
                for ($i = 0; $i < $maxLen; $i++) {
                    $aPart = $aParts[$i] ?? 0;
                    $bPart = $bParts[$i] ?? 0;
                    if ($aPart < $bPart) return -1;
                    if ($aPart > $bPart) return 1;
                }
                return 0;
            }
    
            // if both start with alphabet compare
            return strcmp($aTitle, $bTitle);
        });
    
        // if it has children sort them recursively
        foreach ($items as &$item) {
            if (!empty($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->sortItems($item['children']);
            }
        }
    
        return $items;
    }
    
    function renderMarkdownList($items, $listType, $depth, $level = 0) {
        $output = '';
        $indent = str_repeat("  ", $level);

        if ($level === $depth) {
            return $output;
        }
    
        foreach ($items as $item) {
            switch ($listType){
                case self::LIST_TYPE_NONE:
                    $output .=  $indent . "\n";
                    break;
                default:
                    $output .= $indent . '- ';
                    break;
            }

            if ($item['link']) {
                $output .= '[' . $item['title'] . '](' . $item['link'] . ')';
            } else {
                $output .= $item['title'];
            }
            $output .= "\n";
            if (!empty($item['children'])) {
                $output .= $this->renderMarkdownList($item['children'], $listType, $depth, $level + 1);
            }
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