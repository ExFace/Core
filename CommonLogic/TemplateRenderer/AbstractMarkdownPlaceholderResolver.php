<?php

namespace exface\Core\CommonLogic\TemplateRenderer;

use exface\Core\CommonLogic\QueryBuilder\RowDataArraySorter;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\DataTypes\StringDataType;

abstract class AbstractMarkdownPlaceholderResolver extends AbstractPlaceholderResolver 
{
    protected function getDocsPath(string $currentPagePath) : string
    {
        $rootDir = FilePathDataType::findFolderPath($currentPagePath);
        $normalizedFull = FilePathDataType::normalize($rootDir);
        $parts = explode("/Docs/", string: $normalizedFull);
        if(count($parts) == 1) {
            return $normalizedFull;
        }
        return $parts[0]. "/Docs/";
    }
    
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
    
                if (file_exists($folderIndex)) {
                    $title = MarkdownDataType::findHeadOfFile($folderIndex);
                    $link = $relativePath . $this->relativePath($folderIndex, $directory);
                } else {
                    $title = ucfirst(str_replace('_', ' ', $file));
                    $link = null;
                }
    
                $items[] = [
                    'type' => 'directory',
                    'title' => $title,
                    'sort_key' => $this->extractSortKey($title),
                    'link' => $link,
                    'full_path' => $folderIndex,
                    'relative_path' => $this->relativePath($filePath, $directory),
                    'children' => $this->scanMarkdownDirectory($filePath, $maxDepth, $currentDepth + 1, $relativePath . $file),
                ];
            } elseif (pathinfo($filePath, PATHINFO_EXTENSION) === 'md') {
                $title = MarkdownDataType::findHeadOfFile($filePath);
                $items[] = [
                    'type' => 'file',
                    'title' => $title,
                    'sort_key' => $this->extractSortKey($title),
                    'link' => $relativePath . $this->relativePath($filePath, $directory),
                    'full_path' => $filePath,
                    'relative_path' => $relativePath . $this->relativePath($filePath, $directory),
                ];
            }
        }
        $sorter = new RowDataArraySorter();
        $sorter->addCriteria('sort_key', SORT_ASC);
        return $sorter->sort($items);
    }

    /**
     * Extracts a zero-padded sort key from a numbered title for natural ordering.
     *
     *  Supports arbitrary depth (e.g. 1, 1.2, 1.10.3) and falls back
     *  to alphabetical ordering for unnumbered titles.
     *
     *  Examples:
     *    "1.10 Foo"  -> "00001.00010"
     *    "1.2.1 Bar" -> "00001.00002.00001"
     *    "Intro"     -> "zzzzz.Intro"
     * @param string $title
     * @return string
     */
    private function extractSortKey(string $title): string
    {
        if (preg_match('/^([\d]+(?:\.[\d]+)*)/', trim($title), $matches)) {
            $parts = explode('.', $matches[1]);
            $padded = array_map(fn($p) => str_pad($p, 5, '0', STR_PAD_LEFT), $parts);
            return implode('.', $padded);
        }

        return 'zzzzz.' . $title;
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
        $normalizedFull = FilePathDataType::normalize($fullPath);
        $normalizedFolder = FilePathDataType::normalize($folderPath);
        $normalizedFolder = rtrim($normalizedFolder, '/') . '/';
        return StringDataType::substringAfter($normalizedFull, $normalizedFolder, null);
    }
}