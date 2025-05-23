<?php
namespace exface\Core\Facades\DocsFacade\Placeholders;

use DOMDocument;
use DOMXPath;
use exface\Core\CommonLogic\TemplateRenderer\AbstractMarkdownPlaceholderResolver;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

class ImageListResolver extends AbstractMarkdownPlaceholderResolver implements PlaceholderResolverInterface
{
    const OPTION_LIST_TYPE = "list-type";

    const LIST_TYPE_NONE = "none";

    const LIST_TYPE_BULLET = "bullet";

    private $pagePath = null;
    private $optionDefaults = [
        self::OPTION_LIST_TYPE => self::LIST_TYPE_BULLET,
    ];

    public function __construct(string $pagePathAbsolute, string $prefix = 'ImageList:') {
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
        $rootDirectory = FilePathDataType::findFolderPath($this->pagePath);
        $names = array_map(fn($ph) => $ph['name'], $placeholders);
        $filteredNames = $this->filterPlaceholders($names);
        foreach ($placeholders as $i => $placeholder) {
            if (in_array($placeholder['name'], $filteredNames)) {
                $options = $placeholder['options'];
                parse_str($options, $optionsArray);
            	$markdownStructure = $this->getFlattenMarkdownFiles($rootDirectory);
                $val = $this->renderMarkdownList($markdownStructure, listType: $this->getOption('list-type',$optionsArray));
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
        return $value ?? $default;
    }
        
    function renderMarkdownList($files, $listType) {
        $output = '';
        $listStyle = '';
        $tocItems = [];
        $output .= '<div class="list-wrapper">' . "\n";
        

        switch ($listType) {
            case self::LIST_TYPE_NONE:
                $listStyle = " style=\"list-style-type: none; padding-left: 0;\"";
                break;
            default:
                $listStyle = '';
                break;
        }

        $output .= "<ul$listStyle>\n";
        foreach ($files as $filePath) {
    
            $originalHtml = file_get_contents($filePath);
            $relativePath = $this->relativePath($filePath);
            if (empty(trim($originalHtml))) {
                continue;
            }
    
            $imageIndex = 1;
            $updatedHtml = $originalHtml;
            // catch all image-container blocks
            preg_match_all('/<div\s+class="image-container"(.*?)<\/div>\s*<\/div>/si', $originalHtml, $matches, PREG_OFFSET_CAPTURE);
    
            foreach ($matches[0] as $match) {
                $fullBlock = $match[0];
    
                libxml_use_internal_errors(true);
                $dom = new DOMDocument();
                $dom->loadHTML(mb_convert_encoding($fullBlock, 'HTML-ENTITIES', 'UTF-8'));
                libxml_clear_errors();
    
                $xpath = new DOMXPath($dom);
                $container = $xpath->query('//div[contains(@class, "image-container")]')->item(0);
    
                if (!$container) continue;
    
                $existingId = $container->getAttribute('id');
                // create Id if there is not already
                if (!$existingId) {
                    $newId = $this->findNextImageContainerId($updatedHtml, $relativePath);
                    $container->setAttribute('id', $newId);
                    $existingId = $newId;
                }
    
                $captionDiv = $xpath->query('.//div[contains(@class, "caption")]', $container)->item(0);
                if ($captionDiv) {
                    $captionText = trim(preg_replace('/\s+/', ' ', $captionDiv->textContent));
                    $tocItems[] = [
                        'href' => $this->relativePath($filePath) . '#' . $existingId,
                        'text' => $captionText
                    ];
                }
    
                // create updated image-container HTML
                $newBlock = '';
                foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $child) {
                    $newBlock .= $dom->saveHTML($child);
                }
    
                $updatedHtml = str_replace($fullBlock, $newBlock, $updatedHtml);
    
                $imageIndex++;
            }
    
            file_put_contents($filePath, $updatedHtml);
        }

        foreach ($tocItems as $item) {
            $href = htmlspecialchars($item['href']);
            $text = htmlspecialchars($item['text']);
            $output .= "<li><a href=\"$href\">$text</a></li>" . PHP_EOL;
        }

        $output .= "</ul>\n";
        $output .= '</div>' . "\n";
        
        return $output;
    }

    function findNextImageContainerId(string $markdown, string $fileBase): string 
    {
        $relative = str_replace('.md', '', $fileBase);
        $relative = str_replace(['/', '\\'], '-', $relative);
      
        $pattern = '/<div\s+class="image-container"\s+id="' . preg_quote($relative, '/') . '-image-(\d+)"/i';
        preg_match_all($pattern, $markdown, $matches);
        
        if (empty($matches[1])) {
            return $relative . '-image-' . 1;
        }
    
        $numbers = array_map('intval', $matches[1]);
        return $relative . '-image-' . max($numbers) + 1;
    }
}