<?php
namespace exface\Core\Facades\DocsFacade\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractMarkdownPlaceholderResolver;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

class ImageReferenceResolver extends AbstractMarkdownPlaceholderResolver implements PlaceholderResolverInterface
{
    
    const OPTION_IMAGE_ID = "image-id";
    private $pagePath = null;

    public function __construct(string $pagePathAbsolute, string $prefix = 'ImageRef:') {
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
        $rootDirectory = $this->getDocsPath();
        $markdownStructure = $this->getFlattenMarkdownFiles($rootDirectory);
            
        $names = array_map(fn($ph) => $ph['name'], $placeholders);
        $filteredNames = $this->filterPlaceholders($names);
        foreach ($placeholders as $i => $placeholder) {
            if (in_array($placeholder['name'], $filteredNames)) {
                $options = $placeholder['options'];
                parse_str($options, $optionsArray);
                $imageId = $this->getOption('image-id',$optionsArray);
                if ($imageId !== null) {
                    $val = $this->getImageCaption($markdownStructure, $this->pagePath, $imageId, $i);
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
        return $value ?? $default;
    }
    
    function getImageCaption(array $files, string $selectedFile, string $imageId, int $order) 
    {
        $searchFiles = array_merge([$selectedFile], array_diff($files, [$selectedFile]));
        foreach ($searchFiles as $file) {
            $referenceText = $this->searchTheFile($file, $imageId);
            if(!empty($referenceText)) {
                return $referenceText;
            }            
        }
        return '';
    }

    protected function searchTheFile(string $filePath, string $imageId)
    {
        $content = file_get_contents($filePath);
        $pattern = '/<div\s+class="image-container"\s+id="' . preg_quote($imageId, '/') . '".*?<\/div>\s*<\/div>/si';
        if (preg_match($pattern, $content, $matches)) {
            $block = $matches[0];
            $captionPattern = '/<!--\s*BEGIN\s+ImageCaptionNr:\s*-->\s*Abbildung\s+(\d+):\s*<!--\s*END\s+ImageCaptionNr\s*-->\s*(.*?)\s*<\/div>/si';
            if (preg_match($captionPattern, $block, $captionMatch)) {
                $abbildungNumber = trim($captionMatch[1]);
                $captionText = trim($captionMatch[2]);
                return "Abbildung {$abbildungNumber}: {$captionText}";
            }
        }
    }

    protected function getDocsPath() : string
    {
        $rootDir = FilePathDataType::findFolderPath($this->pagePath);
        $normalizedFull = FilePathDataType::normalize($rootDir);
        $parts = explode("/Docs/", string: $normalizedFull);
        if(count($parts) == 1) {
            return $normalizedFull;
        }
        return $parts[0]. "/Docs/";
    }
}