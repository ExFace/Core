<?php
namespace exface\Core\Facades\DocsFacade;

use GuzzleHttp\Psr7\Query;
use kabachello\FileRoute\Interfaces\ContentInterface;
use kabachello\FileRoute\FileReaders\MarkdownReader;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\StringDataType;

class MarkdownDocsReader extends MarkdownReader 
{    
    private $workbench = null;
    
    public function __construct(WorkbenchInterface $workbench) {
        $this->workbench = $workbench;       
    }
    
    public function readFile(string $filePath, string $urlPath): ContentInterface
    {
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
        } 
        
        if (isset($query) && substr($query, 0, 1) === 'q') {
            $search = $this->buildSearchResult($query);
            $searchPath = $this->workbench->filemanager()->getPathToCacheFolder() . DIRECTORY_SEPARATOR . 'search.md';
            file_put_contents($searchPath, $search);
            $filePath = $searchPath;
        }
        
        return parent::readFile($filePath, $urlPath);
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