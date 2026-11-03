<?php
namespace exface\Core\Facades\DocsFacade;

use GuzzleHttp\Psr7\Query;
use kabachello\FileRoute\Interfaces\ContentInterface;
use kabachello\FileRoute\FileReaders\MarkdownReader;
use kabachello\FileRoute\Exceptions\PageNotFoundException;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\FileNotFoundError;
use exface\Core\Exceptions\FileNotReadableError;
use exface\Core\Exceptions\FilePathNotAllowedError;

class MarkdownDocsReader extends MarkdownReader 
{    
    private $workbench = null;
    
    public function __construct(WorkbenchInterface $workbench) {
        $this->workbench = $workbench;       
    }
    
    /**
     * Reads URL-derived documentation only from vendor so traversal cannot disclose installation files.
     *
     * Generated index and search markdown remains in memory because its cache path is not a URL-selected source.
     *
     * {@inheritDoc}
     * @see \kabachello\FileRoute\FileReaders\MarkdownReader::readFile()
     *
     * @param string $filePath
     * @param string $urlPath
     * @return ContentInterface
     */
    public function readFile(string $filePath, string $urlPath): ContentInterface
    {
        $generatedMarkdown = null;
        if (strpos($urlPath, '?') !== false) {
            $query = StringDataType::substringAfter($urlPath, '?');
            $urlPath = StringDataType::substringBefore($urlPath, '?');
            $filePath = StringDataType::substringBefore($filePath, '?');
        }
        
        if ($urlPath === '' || $urlPath === '/' || $urlPath === 'index.md') {
            $index = $this->buildMarkdownIndex();
            $indexPath = $this->workbench->filemanager()->getPathToCacheFolder() . DIRECTORY_SEPARATOR . 'index.md';
            file_put_contents($indexPath, $index);
            $filePath = $indexPath;
            $generatedMarkdown = $index;
        } 
        
        if (isset($query) && substr($query, 0, 1) === 'q') {
            $search = $this->buildSearchResult($query);
            $searchPath = $this->workbench->filemanager()->getPathToCacheFolder() . DIRECTORY_SEPARATOR . 'search.md';
            file_put_contents($searchPath, $search);
            $filePath = $searchPath;
            $generatedMarkdown = $search;
        }
        
        try {
            $markdown = $generatedMarkdown
                ?? $this->workbench->filemanager()->readFileSecure($filePath, $this->getAllowedBasePaths());
        } catch (FileNotFoundError | FileNotReadableError | FilePathNotAllowedError $e) {
            throw new PageNotFoundException('Page "' . $urlPath . '" not found!', 0, $e);
        }
        // Render markdown through the central MarkdownContent so the DocsFacade uses the same
        // parser configuration (incl. the `enable_newlines` default) as the rest of the workbench.
        $fileFolder = pathinfo($filePath, PATHINFO_DIRNAME);
        $urlFolder = pathinfo($urlPath, PATHINFO_DIRNAME);
        if ($urlFolder === '.') {
            $urlFolder = '';
        }
        $folder = $this->readFolder($fileFolder, $urlFolder);
        return new MarkdownContent($filePath, $urlPath, $folder, $markdown);
    }

    /**
     * Returns the proposed default documentation roots, pending team decision.
     *
     * Vendor is the documented source and intentionally excludes configuration and other installation files.
     *
     * @return string[]
     */
    protected function getAllowedBasePaths() : array
    {
        return [$this->workbench->filemanager()->getPathToVendorFolder()];
    }
    
    protected function buildMarkdownIndex()
    {
        $md = "# Installed apps \n";
        
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.APP');
        $ds->getColumns()->addFromExpression('ALIAS');
        $ds->getColumns()->addFromExpression('NAME');
        $ds->getSorters()->addFromString('NAME', SortingDirectionsDataType::ASC);
        
        $ds->dataRead();
        
        foreach ($ds->getRows() as $row) {
            $appDocIndex = str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, '/', $row['ALIAS']) . '/Docs/index.md';
            $md .= " - [{$row['NAME']}]({$appDocIndex}) \n";
        }
        
        return $md;
    }
    
    protected function buildSearchResult($query)
    {
        $params = Query::parse($query);
        $q = urldecode($params['q']);
        $md = "# Search for \"{$this->escapeString($q)}\" \n";
        
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.DOCS');
        $ds->getColumns()->addFromExpression('PATHNAME_RELATIVE');
        $ds->getColumns()->addFromExpression('PATHNAME_ABSOLUTE');
        $ds->getColumns()->addFromExpression('NAME');
        $ds->getFilters()->addConditionFromString('CONTENTS', $q);
        
        $ds->dataRead();
        
        foreach ($ds->getRows() as $row) {
            $content = $this->readFile($row['PATHNAME_ABSOLUTE'], $row['PATHNAME_RELATIVE']);
            $path = $row['PATHNAME_RELATIVE'] ?? '';
            $md .= <<<MD
 - [{$this->escapeString($content->getTitle() ?? '')}]({$this->escapeString($path)})
    ({$this->escapeString($path)})

MD;
        }
        
        return $md;
    }
    
    /**
     * 
     * @param string $val
     * @return string
     */
    protected function escapeString(string $val) : string
    {
        return htmlspecialchars($val, ENT_QUOTES);
    }
}