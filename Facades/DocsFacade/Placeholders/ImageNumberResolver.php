<?php
namespace exface\Core\Facades\DocsFacade\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

class ImageNumberResolver extends AbstractPlaceholderResolver implements PlaceholderResolverInterface
{
    private $pagePath = null;

    public function __construct(string $pagePathAbsolute, string $prefix = 'ImageCaptionNr:') {
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
                $val = $this->countImagesAndUpdate($markdownStructure, $this->pagePath, $i);
                $vals[$i] = $val;
            }
        }
        return $vals;
    }
    
    function countImagesAndUpdate($files, $targetFile, $order) {
        $currentAbbildungNumber = 1;
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $pattern = '/
                        <div\s+class="image-container"(?:\s+id="[^"]*")?>
                        (?:
                            \s*<img[^>]*>\s*
                            (?:<br\s*\/?>\s*)*
                        )+
                        \s*<div\s+class="caption">\s*
                        (?:<!--\s*BEGIN\s+ImageCaptionNr:\s*-->\s*)?
                        (?:Abbildung\s+(\d+):)?\s*
                        (?:<!--\s*END\s+ImageCaptionNr\s*-->\s*)?
                        (.*?)
                        <\/div>\s*<\/div>
                        /six';

            preg_match_all($pattern, $content, $matches);
            
            if (FilePathDataType::normalize($file) === FilePathDataType::normalize($targetFile) && count($matches[0]) > 0) {
                return "Abbildung " . $currentAbbildungNumber + $order . ":";
            }

            $currentAbbildungNumber += count($matches[0]);
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