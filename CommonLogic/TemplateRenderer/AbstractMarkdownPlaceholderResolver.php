<?php

namespace exface\Core\CommonLogic\TemplateRenderer;

use exface\Core\CommonLogic\QueryBuilder\RowDataArraySorter;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\DataTypes\StringDataType;

abstract class AbstractMarkdownPlaceholderResolver extends AbstractPlaceholderResolver 
{
    protected function scanMarkdownDirectory(string $directory, int $maxDepth = PHP_INT_MAX, int $currentDepth = 0, $relativePath = ''): array {
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
            $relativePath = rtrim($relativePath, '/');
            if ($relativePath !== '') {
                $relativePath .= '/';
            }
    
            if (is_dir($filePath)) {
                if (in_array($file, ['Bilder', 'Archive', 'Intro'])) {
                    continue;
                }
    
                $folderIndex = $filePath . DIRECTORY_SEPARATOR . 'index.md';
                $title = ucfirst($file);
                $link = null;
    
                if (file_exists($folderIndex)) {
                    $title = MarkdownDataType::findHeadOfFile($folderIndex);
                    $link = $this->relativePath($folderIndex, $directory);
                }
    
                $items[] = [
                    'type' => 'directory',
                    'title' => $title,
                    'link' => $link,
                    'full_path' => $folderIndex,
                    'relative_path' => $this->relativePath($filePath, $directory),
                    'children' => $this->scanMarkdownDirectory($filePath, $maxDepth, $currentDepth + 1, $relativePath . $file),
                ];
            } elseif (pathinfo($filePath, PATHINFO_EXTENSION) === 'md') {
                $items[] = [
                    'type' => 'file',
                    'title' => MarkdownDataType::findHeadOfFile($filePath),
                    'link' => $relativePath . $this->relativePath($filePath, $directory),
                    'full_path' => $filePath,
                    'relative_path' => $relativePath . $this->relativePath($filePath, $directory),
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

    /**
     * @param string $fullPath
     * @param string $folderPath
     * @return string
     */
    protected function relativePath(string $fullPath, string $folderPath = '/Docs/'): string
    {
        $marker = 'Docs';
        $normalizedFull = FilePathDataType::normalize($fullPath);
        $normalizedFolder = FilePathDataType::normalize($folderPath);
        $normalizedFolder = rtrim($normalizedFolder, '/') . '/';
        return StringDataType::substringAfter($normalizedFull, $normalizedFolder, null);
    }
}