<?php

namespace exface\Core\CommonLogic\TemplateRenderer;

use exface\Core\CommonLogic\QueryBuilder\RowDataArraySorter;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\TemplateRenderers\PrefixedPlaceholderResolverInterface;

abstract class AbstractPlaceholderResolver 
    implements PlaceholderResolverInterface, PrefixedPlaceholderResolverInterface
{
    protected string $prefix = '';

    /**
     * @return string
     */
    public function getPrefix() : string
    {
        return $this->prefix;
    }

    protected function setPrefix(string $prefix) : PlaceholderResolverInterface
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     *
     * @param string[] $placeholders
     * @param string $prefix
     * @return string[]
     */
    protected function filterPlaceholders(array $placeholders) : array
    {
        return array_filter($placeholders, function($ph) {
            return StringDataType::startsWith($ph, $this->getPrefix());
        });
    }

    /**
     *
     * @param string $placeholder
     * @param string $prefix
     * @return string
     */
    protected function stripPrefix(string $placeholder) : string
    {
        $prefix = $this->getPrefix();
        if ($prefix === '') {
            return $placeholder;
        }
        return StringDataType::substringAfter($placeholder, $prefix);
    }

    protected function scanMarkdownDirectory(string $directory, int $maxDepth = PHP_INT_MAX, int $currentDepth = 0): array {
        if ($currentDepth > $maxDepth) {
            return [];
        }
    
        $items = [];
        $files = scandir($directory);
    
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || strtolower($file) === 'index.md') {
                continue;
            }
    
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;
    
            if (is_dir($filePath)) {
                if (in_array($file, ['Bilder', 'Archive'])) {
                    continue;
                }
    
                $folderIndex = $filePath . DIRECTORY_SEPARATOR . 'index.md';
                $title = ucfirst($file);
                $link = null;
    
                if (file_exists($folderIndex)) {
                    $title = MarkdownDataType::findHeadOfFile($folderIndex);
                    $link = $this->relativePath($folderIndex);
                }
    
                $items[] = [
                    'type' => 'directory',
                    'title' => $title,
                    'link' => $link,
                    'full_path' => $folderIndex,
                    'relative_path' => $this->relativePath($filePath),
                    'children' => $this->scanMarkdownDirectory($filePath, $maxDepth, $currentDepth + 1),
                ];
            } elseif (pathinfo($filePath, PATHINFO_EXTENSION) === 'md') {
                $items[] = [
                    'type' => 'file',
                    'title' => MarkdownDataType::findHeadOfFile($filePath),
                    'link' => $this->relativePath($filePath),
                    'full_path' => $filePath,
                    'relative_path' => $this->relativePath($filePath),
                ];
            }
        }
        $sorter = new RowDataArraySorter();
        $sorter->addCriteria('title', SORT_ASC);
        return $sorter->sort($items);
    }
    
    function getFlattenMarkdownFiles(string $rootDir) : array
    {
        $rawList = $this->scanMarkdownDirectory($rootDir);
        $flatList = [];

        $flatten = function(array $items) use (&$flatten, &$flatList) {
            foreach ($items as $item) {
                $flatList[] = $item['full_path'];
                if ($item['type'] === 'directory') {
                    $flatten($item['children']);
                }
            }
        };
    
        $flatten($rawList);
    
        return $flatList;
    }
    
    protected function relativePath(string $fullPath): string
    {
        $marker = 'Docs';
        $normalizedFull = FilePathDataType::normalize($fullPath);
        $parts = explode("/$marker/", $normalizedFull);

        return isset($parts[1]) ? $parts[1] : null;
    }
}