<?php
namespace exface\Core\Facades\DocsFacade\Placeholders;

use exface\Core\CommonLogic\QueryBuilder\RowDataArraySorter;
use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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
        $markdownStructure = $this->getOrderedMarkdownFiles($rootDirectory);
            
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

    function getOrderedMarkdownFiles($rootDir) : array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $folderGroups = [];

        $sorter = new RowDataArraySorter();
        $sorter->addCriteria('title', SORT_ASC);
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) continue;
            if (strtolower($fileInfo->getExtension()) !== 'md') continue;

            $path = $fileInfo->getPathname();
            $folder = dirname($path);

            if (!isset($folderGroups[$folder])) {
                $folderGroups[$folder] = [
                    'index' => null,
                    'files' => [],
                ];
            }

            if (strtolower($fileInfo->getFilename()) === 'index.md') {
                $folderGroups[$folder]['index'] = $path;
            } else {
                $title = MarkdownDataType::findHeadOfFile($path);
                $folderGroups[$folder]['files'][] = [
                    'path' => $path,
                    'title' => $title
                ];
            }
        }

        foreach ($folderGroups as $group) {
            if ($group['index']) {
                $result[] = $group['index'];
            }

            $group['files'] = $sorter->sort($group['files']);

            foreach ($group['files'] as $item) {
                $result[] = $item['path'];
            }
        }

        return $result;
    }
    
    function countImagesAndUpdate($files, $targetFile, $order) {
        $currentAbbildungNumber = 1;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $pattern = '/
                        <div\s+class="image-container"(?:\s+id="[^"]*")?>
                        (?:\s*<img[^>]*>)+
                        \s*<div\s+class="caption">\s*
                        (?:<!--\s*BEGIN\s+ImageCaptionNr:\s*-->\s*)?
                        Abbildung\s+(\d+):\s*
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