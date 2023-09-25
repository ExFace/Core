<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilter;
use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\BinaryDataType;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\QueryBuilder\QueryPartValue;
use exface\Core\CommonLogic\DataQueries\FileWriteDataQuery;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\CommonLogic\DataQueries\FileReadDataQuery;

/**
 * Lists files and folders from a number of file paths.
 * 
 * Object instances are files and folders. Attributes are file properties
 * including the content. Since all files have the same types of properties, 
 * there is a generic meta object `exface.Core.FILE` that already contains all
 * the attriutes - see details below. Extend this object for convenience.
 * 
 * There are also built-in data sources for files in the current installation
 * ("Local Folders") and files in the vendor folder ("Local Vendor Folders").
 * They provide the `FILE` object as base object. See core object `TRANLATIONS`
 * for usage example.
 * 
 * ## Data Addresses
 * 
 * ### Objects
 * 
 * Objects are files and folders. Object data addresses are file system paths 
 * with forward slashes (/) as directory separators. Wildcards are supported:
 * e.g. `folder/* /subfolder/*.json` or `folder/*`.
 * 
 * Placeholders can be used in object addresses. This will result in required
 * filters, similary to URL builders in the UrlDataConnector. For example,
 * `myfolder/[#FOLDER_NAME#]/*` will mean, that a filter for the folder name
 * is required and  
 * 
 * ### Attributes 
 * 
 * Attributes are file/folder properties. The data addresses allow to address properties of a file like
 * `~file:name` or of the containing folder via `~folder:name`. Most addresses except for content-related
 * data are available in both cases.
 * 
 * Using the `~folder:` prefix basically shifts the focus one level up the file tree. You can also chain it:
 * `~folder:~folder:name` will giv you the name of the second folder up the hierarchy. 
 * 
 * The following data addresses are available:
 * 
 * - `~file:name` - file name with extension
 * - `~folder:name` - name of the containing folder
 * - `~file:path_relative` - path to the file relative to the base path of the connector
 * - `~folder:path_relative` - path to the folder containing the file relative to the base path of the connector
 * - `~file:path_absolute` - absolute file path
 * - `~file:extension`
 * - `~file:name_without_extension`
 * - `~file:mtime` - last modification time
 * - `~file:ctime` - creation time
 * - `~file:mimetype` - MIME type of the file - e.g. `text/plain`
 * - `~file:subpath(start,length)` - extracts a subset of the folder path (excl. the filenam): e.g.
 * `subpath(0,2)` from the path `exface/Core/Translations/Objects` would yield `exface/Core`,
 * while `subpath(0,-1)` would produce `exface/Core/Translations`, `subpath(2)` - `Translations/Objects`
 * and `subpath(-1)` - `Objects`
 * - `~file:is_file`
 * - `~file:is_folder`
 * - `~file:is_link`
 * - `~folder:~folder:path_relative`
 * - `~file:line(n)` - n-th line of the file starting with 1: e.g. `line(1)` to get the first line. This only works with files!
 * - `~file:content` - the entire content of the file as a string
 * 
 * ## Built-in FILE-object
 * 
 * The core app contains the meta object `exface.Core.FILE` to use with this query builder.
 * 
 * **NOTE**: the UID-attribute of the `FILE` object is it's relative pathname. The UID is
 * unique within the base path of the data connection.
 * 
 * ## Limitations
 *  
 * In case there are files in different folders with the same name and files of both folders will be selected,
 * with one of the selected files being such a file the FileBuilder will find also the file in the other folder with that name.
 * This can for example lead to files being accidentally deleted in a folder because they have the same name as a file of another folder
 * but files in the first folder also were selected to delete.
 *
 * 
 * @author Andrej Kabachnik
 *        
 */
class FileBuilder extends AbstractQueryBuilder
{
    
    /**
     * If set to TRUE request without at least a single filter are skipped returning an empty result automatically.
     *
     * @uxon-property force_filtering
     * @uxon-target object
     * @uxon-type boolean
     */
    const DAP_FORCE_FILTERING = 'force_filtering';
    
    /**
     * Restrict the depth of traversing folders.
     * 
     * By default the depth is unlimited. Set `folder_depth` to `0` to ignore subfolders
     * completely, `1` will only allow immediate subfolders, etc.
     *
     * @uxon-property folder_depth
     * @uxon-target object
     * @uxon-type boolean
     */
    const DAP_FOLDER_DEPTH = 'folder_depth';
    
    /**
     * Set to TRUE to delete an empty folder after the last file in it was deleted by this query builder.
     *
     * @uxon-property delete_empty_folders
     * @uxon-target object
     * @uxon-type boolean
     * @uxon-default false
     */
    const DAP_DELETE_EMPTY_FOLDERS = 'delete_empty_folders';
    
    
    const ATTR_ADDRESS_PREFIX_FILE= '~file:';
    
    const ATTR_ADDRESS_PREFIX_FOLDER = '~folder:';
    
    const ATTR_ADDRESS_PATH_ABSOLUTE = 'path_absolute';
    
    const ATTR_ADDRESS_PATH_RELATIVE = 'path_relative';
    
    const ATTR_ADDRESS_LINE = 'line';
    
    const ATTR_ADDRESS_SUBPATH = 'subpath';
    
    const ATTR_ADDRESS_CONTENT = 'content';
    
    const ATTR_ADDRESS_NAME = 'name';
    
    const ATTR_ADDRESS_NAME_WITHOUT_EXTENSION = 'name_without_extension';
    
    const ATTR_ADDRESS_EXTENSION = 'extension';
    
    const ATTR_ADDRESS_SIZE = 'size';
    
    const ATTR_ADDRESS_MIMETYPE = 'mimetype';
    
    const ATTR_ADDRESS_MTIME = 'mtime';
    
    const ATTR_ADDRESS_CTIME = 'ctime';
    
    const ATTR_ADDRESS_IS_FILE= 'is_file';
    
    const ATTR_ADDRESS_IS_FOLDER = 'is_folder';
    
    const ATTR_ADDRESS_IS_LINK = 'is_link';
    
    const ATTR_ADDRESS_IS_READABLE = 'is_readable';
    
    const ATTR_ADDRESS_IS_WRITABLE = 'is_writable';
    
    
    /**
     * 
     * @return FileReadDataQuery
     */
    protected function buildQueryToRead() : FileReadDataQuery
    {
        $query = new FileReadDataQuery();
        
        $path_patterns = $this->buildPathPatternFromFilterGroup($this->getFilters(), $query);
        $filename = $this->buildFilenameFromFilterGroup($this->getFilters(), $query);
        
        // Setup query
        foreach ($path_patterns as $path) {
            if ($path == '') {
                $path = $this->getMainObject()->getDataAddress();
            }
            $last_slash_pos = mb_strripos($path, '/');
            if ($last_slash_pos === false) {
                $pathRelative = $path;
            } else {
                $pathRelative = substr($path, 0, $last_slash_pos);
                $name = $filename ? $filename : substr($path, ($last_slash_pos + 1));
            }
            if (! is_null($name) && $name !== '') {
                $query->addFilenamePattern($name);
            }
            
            $query->addFolder($pathRelative);            
        }
        
        if (count($this->getSorters()) > 0) {
            $query->setFullScanRequired(true);
            // All the sorting is done locally
            foreach ($this->getSorters() as $qpart) {
                $qpart->setApplyAfterReading(true);
            }
        }
        
        $depth = $this->getFolderDepth();
        if ($depth !== null) {
            $query->setFolderDepth($depth);
        }
        
        return $query;
    }
    
    protected function isFilename(QueryPartAttribute $qpart) : bool
    {
        $addr = mb_strtolower(trim($qpart->getDataAddress()));
        return $addr === FileBuilder::ATTR_ADDRESS_NAME || $addr === 'name' || $addr === 'filename';
    }
    
    /**
     * Returns TRUE if give query part references the contents of a file and FALSE otherwise
     *
     * @param QueryPartAttribute $qpart
     * @return bool
     */
    protected function isFileContent(QueryPartAttribute $qpart) : bool
    {
        $addr = mb_strtolower(trim($qpart->getDataAddress()));
        return $addr === FileBuilder::ATTR_ADDRESS_CONTENTS || $addr === 'contents';
    }
    
    protected function buildFilenameFromFilterGroup(QueryPartFilterGroup $qpart, FileReadDataQuery $query) : ?string
    {
        $values = [];
        $filtersApplied = [];
        $filename = null;
        foreach ($qpart->getFilters() as $filter) {
            if ($this->isFilename($filter)) {
                switch ($filter->getComparator()) {
                    case EXF_COMPARATOR_EQUALS:
                    case EXF_COMPARATOR_IS:
                        $mask = preg_quote($filter->getCompareValue()) . ($this->isFilename($filter) ? '\\.' : '');
                        if ($filter->getComparator() === EXF_COMPARATOR_EQUALS) {
                            $mask = '^' . $mask . '$';
                        }
                        $values[] = $mask;
                        $filtersApplied[] = $filter;
                        break;
                    default: // Do nothing - the filters will be applied by the query builder after reading files
                }
            }
        }
        
        $values = array_unique($values);
        
        if (! empty($values)) {
            switch ($qpart->getOperator()) {
                case EXF_LOGICAL_OR:
                    $filename = '/(' . implode('|', $values) . ')/i';
                    break;
                case EXF_LOGICAL_AND: 
                    if (count($values) === 1) {
                        $filename = '/' . $values[0] . '/i';
                        break;
                    } 
                default:
                    foreach ($filtersApplied as $filter) {
                        $filter->setApplyAfterReading(true);
                        $query->setFullScanRequired(true);
                    }
            }
        }
        
        foreach ($qpart->getNestedGroups() as $group) {
            if ($filename === null) {
                $filename = $this->buildFilenameFromFilterGroup($group, $query);
            } else {
                $group->setApplyAfterReading(true, function(QueryPartFilter $filter) {
                    return $this->isFilename($filter);
                });
                $query->setFullScanRequired(true);
            }
        }
        
        return $filename;
    }
    
    protected function buildPathPatternFromFilterGroup(QueryPartFilterGroup $qpart, FileReadDataQuery $query) : array
    {
        // See if the data address has placeholders
        $oper = $qpart->getOperator();
        $addr = $this->getMainObject()->getDataAddress();
        $addrPhs = StringDataType::findPlaceholders($addr);
        $pathPatterns = [];
        $uidPatterns = [];
        if ($addr !== null && empty($this->getFilters()->getFilters())) {
            return [$addr];
        }
        // Look for filters, that can be processed by the connector itself
        foreach ($this->getFilters()->getFilters() as $qpart) {
            $addrPhsValues = [];
            $uidPaths = [];
            if (($isPathNameFilter = $qpart->getAttribute()->is($this->getMainObject()->getUidAttribute())) || in_array($qpart->getAlias(), $addrPhs)) {
                // Path filters need to be applied after reading too as there may be trouble with 
                // files with the same name in different (sub-)folders mathing the folder pattern
                if ($isPathNameFilter && $this->getAttribute($qpart->getAlias())) {
                    $qpart->setApplyAfterReading(true);
                }
                // add the base data adress to the patterns if first attribute replacing a placeholder is found
                if (in_array($qpart->getAlias(), $addrPhs)) {
                    if (empty($pathPatterns)) {
                        $pathPatterns[] = $addr;
                    }
                }
                switch ($qpart->getComparator()) {
                    case EXF_COMPARATOR_IS:
                    case EXF_COMPARATOR_EQUALS:
                        //if attribute alias is a placeholder in the path patterns, replace it with the value
                        if (in_array($qpart->getAlias(), $addrPhs)) {                            
                            $addrPhsValues[$qpart->getAlias()] = $qpart->getCompareValue();
                            $newPatterns = [];
                            foreach ($pathPatterns as $pattern) {
                                $newPatterns[] = Filemanager::pathNormalize(StringDataType::replacePlaceholders($pattern, $addrPhsValues, false));
                            }
                            $pathPatterns = $newPatterns;
                        } else {
                            $uidPaths[] = Filemanager::pathNormalize($qpart->getCompareValue());
                        }
                        break;
                    case EXF_COMPARATOR_IN:
                        $values = explode($qpart->getValueListDelimiter(), $qpart->getCompareValue());
                        //if attribute alias is a placeholder in the path patterns, replace it with the values (therefore creating more pattern entries)
                        if (in_array($qpart->getAlias(), $addrPhs)) {
                            foreach ($values as $val) {
                                $addrPhsValues[$qpart->getAlias()] = trim($val);
                                foreach ($pathPatterns as $pattern) {
                                    $newPatterns[] = Filemanager::pathNormalize(StringDataType::replacePlaceholders($pattern, $addrPhsValues, false));
                                }
                            }
                            $pathPatterns = $newPatterns;
                        } else {
                            foreach ($values as $val) {
                                $uidPaths[] = Filemanager::pathNormalize(trim($val));
                            }
                        }
                        break;
                    default:
                        $qpart->setApplyAfterReading(true);
                        $query->setFullScanRequired(true);
                }
                if ($oper === EXF_LOGICAL_AND) {
                    if (! empty($uidPatterns)) {
                        foreach ($uidPaths as $path) {
                            if (! in_array($path, $uidPatterns)) {
                                throw new QueryBuilderException('Can not add multiple different paths from different "' . EXF_LOGICAL_AND .'" combined filters!');
                            }
                        }
                    } else {
                        $uidPatterns = $uidPaths;
                    }
                } elseif ($oper === EXF_LOGICAL_OR) {
                    $uidPatterns = array_unique(array_merge($uidPatterns, $uidPaths));
                } else {
                    throw new QueryBuilderException('Other filter operators than "' . EXF_LOGICAL_AND . '" or "'. EXF_LOGICAL_OR . '" are not supported by the FileBuilder');
                }
            } else {
                $this->addAttribute($qpart->getExpression()->toString());
                $qpart->setApplyAfterReading(true);
                $query->setFullScanRequired(true);
            }
        }
        foreach ($pathPatterns as $path) {
            if (! empty(StringDataType::findPlaceholders($path))) {
                throw new QueryBuilderException('No filter value given to replace placeholders in path "' . $path . "!'");
            }
        }
        $patterns = [];
        switch (true) {
            case $oper === EXF_LOGICAL_OR:
                $patterns = array_unique(array_merge($pathPatterns, $uidPatterns));
                break;
            case ! empty($pathPatterns) && ! empty($uidPatterns):
                foreach ($pathPatterns as $pathIdx => $pathPattern) {
                    $pathMatchesAllUids  = true;
                    foreach ($uidPatterns as $uidPattern) {
                        if ($uidPattern !== '' && $uidPattern !== null && ! FilePathDataType::matchesPattern($uidPattern, $pathPattern)) {
                            $pathMatchesAllUids = false;
                            break;
                        }
                    }
                    if ($pathMatchesAllUids === true) {
                        unset($pathPatterns[$pathIdx]);
                    } else {
                        throw new QueryBuilderException('Cannot resolve AND-filter over filename and path patterns both at the same time!');
                    }
                }
                if (empty($pathPatterns)) {
                    $patterns = $uidPatterns;
                } else {
                    throw new QueryBuilderException('Cannot resolve AND-filter over filename and path patterns both at the same time!');
                }
                break;
            case ! empty($pathPatterns):
                $patterns = $pathPatterns;
                break;
            default:
                $patterns = $uidPatterns;
        }
        
        return empty($patterns) ? [$addr] : $patterns;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::create()
     */
    public function create(DataConnectionInterface $dataConnection) : DataQueryResultDataInterface
    {
        $basePath = $dataConnection->getBasePath() ?? $this->getWorkbench()->getInstallationPath();
        $fileArray = $this->buildPathsFromValues($basePath);
        if (empty($fileArray)) {
            throw new QueryBuilderException('Cannot create files: no paths specified!');
        }
        foreach ($fileArray as $path) {
            if ($path === null || $path === '') {
                throw new QueryBuilderException('Cannot create file: path is empty!');
            }
        }
        $contentQparts = $this->getValuesForFileContent();
        $query = new FileWriteDataQuery();
        $touchedFilesCnt = 0;
        switch (true) {
            case count($contentQparts) === 1:
                $contentArray = $this->buildFilesContentsFromValues(reset($contentQparts));
                if (count($fileArray) !== count($contentArray)) {
                    throw new QueryBuilderException('Cannot update files: only ' . count($contentArray) . ' of ' . count($fileArray) . ' files exist!');
                }
                foreach ($fileArray as $i => $path) {
                    $query->addFileToSave($path, $contentArray[$i]);
                    $touchedFilesCnt++;
                }
                break;
            case count($contentQparts) > 1:
                $contentAliases = [];
                foreach ($contentQparts as $qpart) {
                    $contentAliases[$qpart->getAlias()];
                }
                throw new QueryBuilderException('Cannot update files with multiple content-related attributes at the same time: ' . implode(', ', $contentAliases));
            default:
                throw new QueryBuilderException('Cannot create files without contents! Please add a data column for file contents.');
        }
        return new DataQueryResultData([], $touchedFilesCnt);
    }
    
    /**
     * 
     * @param QueryPartValue $qpart
     * @return string[]
     */
    protected function buildFilesContentsFromValues(QueryPartValue $qpart) : array
    {
        $array = $qpart->getValues();
        $dataType = $qpart->getDataType();
        foreach ($array as $i => $v) {
            $array[$i] = $dataType->parse($v);
        }
        switch (true) {
            case $dataType instanceof BinaryDataType && $dataType->getEncoding() === BinaryDataType::ENCODING_BASE64:
                array_walk($array, 'base64_decode');
                break;
            default:
                foreach ($array as $i => $val) {
                    if (StringDataType::startsWith($val, 'data:', false) && stripos($val, 'base64,') !== false) {
                        $array[$i] = base64_decode(StringDataType::substringAfter($val, 'base64,'));
                    }
                }
        }
        return $array;
    }
    
    /**
     * Returns all value query parts referencing file contents (i.e. those to save inside files)
     * 
     * @return QueryPartValue[]
     */
    protected function getValuesForFileContent() : array
    {
        $qparts = [];
        foreach ($this->getValues() as $i => $qpart) {
            if ($this->isFileContent($qpart)) {
                $qparts[$i] = $qpart;
            }
        }
        return $qparts;
    }
    
    /**
     * TODO Add an interface for file-based data connections to get their base paths
     * 
     * @param string $basePath
     * @return string[]
     */
    protected function buildPathsFromValues(string $basePath) : array
    {
        switch (true) {
            case ($qpart = $this->getValue('PATHNAME_ABSOLUTE')) && $qpart->hasValues():
                return $qpart->getValues();
            case ($qpart = $this->getValue('PATHNAME_RELATIVE')) && $qpart->hasValues():
                $paths = [];
                foreach ($qpart->getValues() as $rowIdx => $relPath) {
                    if (! FilePathDataType::isAbsolute($relPath)) {
                        $paths[$rowIdx] = FilePathDataType::join([$basePath, $relPath]);
                    } else {
                        $paths[$rowIdx] = $relPath;
                    }
                }
                return $paths;
            case ($qpart = $this->getValue('FILENAME')) && $qpart->hasValues():
                $paths = [];
                $addr = FilePathDataType::normalize($this->getMainObject()->getDataAddress());
                $addr = StringDataType::substringBefore($addr, '/', $addr, false, true);
                $addrPhs = StringDataType::findPlaceholders($addr);
                
                foreach ($qpart->getValues() as $rowIdx => $filename) {
                    $phVals = [];
                    foreach ($addrPhs as $ph) {
                        if ($phQpart = $this->getValue($ph)) {
                            $phVals[$ph] = $phQpart->getValues()[$rowIdx];
                        }
                    }
                    $path = StringDataType::replacePlaceholders($addr, $phVals) . '/' . $filename;
                    if (! FilePathDataType::isAbsolute($path)) {
                        $path = $this->getWorkbench()->getInstallationPath() . DIRECTORY_SEPARATOR . $path;
                    }
                    $paths[$rowIdx] = $path;
                }
                return $paths;
        }
        return [];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::read()
     */
    public function read(DataConnectionInterface $dataConnection) : DataQueryResultDataInterface
    {
        $result_rows = array();
        $pagination_applied = false;
        // Check if force filtering is enabled
        if ($this->getMainObject()->getDataAddressProperty(FileBuilder::DAP_FORCE_FILTERING) && count($this->getFilters()->getFiltersAndNestedGroups()) < 1) {
            return false;
        }
        
        $query = $this->buildQueryToRead();
        $performedQuery = $dataConnection->query($query);
        $rownr = - 1;
        $fullScan = $query->isFullScanRequired();
        $limit = $this->getLimit();
        $offset = $this->getOffset();
        foreach ($performedQuery->getFiles() as $file) {
            // If no full scan is required, apply pagination right away, so we do not even need to reed the files not being shown
            if (! $fullScan) {
                $pagination_applied = true;
                $rownr ++;
                // Skip rows, that are positioned below the offset
                if ($rownr < $this->getOffset()) {
                    continue;
                }
                // Skip rest if we are over the limit
                if ($this->getLimit() > 0 && $rownr >= $offset + $limit) {
                    break;
                }
            }
            // Otherwise add the file data to the result rows
            $result_rows[] = $this->buildResultRow($file);
        }
        $totalCount = count($result_rows);
        
        $result_rows = $this->applyFilters($result_rows);
        $result_rows = $this->applySorting($result_rows);
        if (! $pagination_applied) {
            $result_rows = $this->applyPagination($result_rows);
        }
        
        $rowCount = count($result_rows);
        
        return new DataQueryResultData($result_rows, $rowCount, ($totalCount > $rowCount + $this->getOffset()), $totalCount);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::count()
     */
    public function count(DataConnectionInterface $dataConnection) : DataQueryResultDataInterface
    {
        return $this->read($dataConnection);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::update()
     */
    public function update(DataConnectionInterface $dataConnection) : DataQueryResultDataInterface
    {
        $touchedFilesCnt = 0;
        // Update by path (in one of the values)
        $basePath = $dataConnection->getBasePath() ?? $this->getWorkbench()->getInstallationPath();
        $fileArray = $this->buildPathsFromValues($basePath);
        
        // Update by filters
        if (empty($fileArray) && ! $this->getFilters()->isEmpty()) {
            $fileQuery = new FileBuilder($this->getSelector());
            $fileQuery->setMainObject($this->getMainObject());
            $fileQuery->setFilters($this->getFilters());
            // Read both - absolute and relative paths because the filters may need to be applied after reading,
            // so instead of trying to figure out which attribute will be needed, we just add them both here.
            $fileQuery->addAttribute('PATHNAME_ABSOLUTE');
            $fileQuery->addAttribute('PATHNAME_RELATIVE');
            $fileReadResult = $fileQuery->read($dataConnection);
            foreach ($fileReadResult->getResultRows() as $row) {
                $fileArray[] = $row['PATHNAME_ABSOLUTE'];
                if (count($fileArray) > 1) {
                    throw new QueryBuilderException('Cannot update more than 1 file at a time by filters!');   
                }
            }
        }
        
        // Do the updating
        $query = new FileWriteDataQuery();
        if (empty($fileArray) === false) {
            $contentQparts = $this->getValuesForFileContent();
            switch (true) {
                case count($contentQparts) === 1:
                    $contentArray = $this->buildFilesContentsFromValues(reset($contentQparts));
                    if (count($fileArray) !== count($contentArray)) {
                        throw new QueryBuilderException('Cannot update files: only ' . count($contentArray) . ' of ' . count($fileArray) . ' files exist!');
                    }
                    foreach ($fileArray as $i => $path) {
                        $query->addFileToSave($path, $contentArray[$i]);
                        $touchedFilesCnt++;
                    }
                    break;
                case count($contentQparts) > 1:
                    $contentAliases = [];
                    foreach ($contentQparts as $qpart) {
                        $contentAliases[$qpart->getAlias()];
                    }
                    throw new QueryBuilderException('Cannot update files with multiple content-related attributes at the same time: ' . implode(', ', $contentAliases));
                default:
                    // TODO how to update other file attributes?
            }
        }
        
        return new DataQueryResultData([], $touchedFilesCnt);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::delete()
     */
    public function delete(DataConnectionInterface $dataConnection) : DataQueryResultDataInterface
    {
        $query = new FileWriteDataQuery();
        if (null !== $deleteEmptyFolder = BooleanDataType::cast($this->getMainObject()->getDataAddressProperty(FileBuilder::DAP_DELETE_EMPTY_FOLDERS))) {
            $query->setDeleteEmptyFolders($deleteEmptyFolder);
        }
        
        /* @var FileInfoInterface $file */
        foreach ($dataConnection->query($this->buildQueryToRead())->getFiles() as $file) {
            $query->addFileToDelete($file);
        }
        $performed = $dataConnection->query($query);
        
        return new DataQueryResultData([], $performed->countAffectedRows());
    }

    /**
     * 
     * @param FileInfoInterface $file
     * @throws QueryBuilderException
     * @return string[]|mixed[]
     */
    protected function buildResultRow(FileInfoInterface $file) : array
    {
        $row = [];
        foreach ($this->getAttributes() as $qpart) {
            if ($field = $qpart->getAttribute()->getDataAddress()) {
                $row[$qpart->getColumnKey()] = $this->buildResultValueFromFile($file, $field);
            }
        }
        return $row;
    }
    
    /**
     * 
     * @param FileInfoInterface $file
     * @param string $dataAddress
     * 
     * @throws QueryBuilderException
     * 
     * @return NULL
     */
    protected function buildResultValueFromFile(FileInfoInterface $file, string $dataAddress)
    {
        $fieldLC = mb_strtolower($dataAddress);
        
        // backwards compatibility
        if ($fieldLC === 'path_relative') {
            $fieldLC = self::ATTR_ADDRESS_PREFIX_FOLDER . self::ATTR_ADDRESS_PATH_RELATIVE;
        } elseif ($fieldLC === '~folder' || $fieldLC === 'folder_name') {
            $fieldLC = self::ATTR_ADDRESS_PREFIX_FOLDER . self::ATTR_ADDRESS_NAME;
        }
        
        // Pass ~folder:xxx addresses to the parent folder and handle ~file:xxx here directly
        if (StringDataType::startsWith($fieldLC, self::ATTR_ADDRESS_PREFIX_FOLDER)) {
            $folderAddr = substr($fieldLC, strlen(self::ATTR_ADDRESS_PREFIX_FOLDER));
            $folderAddr = strpos($folderAddr, ':') === false ? self::ATTR_ADDRESS_PREFIX_FILE . $folderAddr : $folderAddr;
            return $this->buildResultValueFromFile($file->getFolderInfo(), $folderAddr);
        } else {
            // For file data addresses translate older notation to new notation and
            // and remove the `~file:` prefix for the current notation
            switch ($fieldLC) {
                // backwards compatibility
                case 'path':
                    $fieldLC = self::ATTR_ADDRESS_PATH_ABSOLUTE;
                    break;
                // backwards compatibility
                case 'name':
                    $fieldLC = self::ATTR_ADDRESS_NAME_WITHOUT_EXTENSION;
                    break;
                // backwards compatibility
                case '~filename':
                case 'filename':
                    $fieldLC = self::ATTR_ADDRESS_NAME;
                    break;
                // backwards compatibility
                case '~filename_without_extension':
                    $fieldLC = self::ATTR_ADDRESS_NAME_WITHOUT_EXTENSION;
                    break;  
                // backwards compatibility
                case '~filepath':
                case 'pathname_absolute':
                    $fieldLC = self::ATTR_ADDRESS_PATH_ABSOLUTE;
                    break;
                // backwards compatibility
                case '~filepath_relative':
                case 'pathname_relative':
                    $fieldLC = FileBuilder::ATTR_ADDRESS_PATH_RELATIVE;
                    break;
                // backwards compatibility
                case '~contents':
                case 'contents':
                    $fieldLC = self::ATTR_ADDRESS_CONTENT;
                    break;
                // backwards compatibility
                case '~extension':
                    $fieldLC = self::ATTR_ADDRESS_EXTENSION;
                    break;
                // Current data addresses with `~file:xxx` - remove the `~file:` prefix
                // If it is not there, just keep the address as-is. This might happen for
                // legacy addresses like `extension`, that did not have prefixes.
                default:
                    $fieldLC = StringDataType::substringAfter($fieldLC, self::ATTR_ADDRESS_PREFIX_FILE, $fieldLC);
                    break;
            }
        }
        
        $value = null;
        
        // simple properties
        switch ($fieldLC) {
            case self::ATTR_ADDRESS_NAME:
                $value = $file->getFilename(true);
                break;
            case self::ATTR_ADDRESS_NAME_WITHOUT_EXTENSION:
                $value = $file->getFilename(false);
                break;
            case self::ATTR_ADDRESS_PATH_ABSOLUTE:
                $value = $file->getPathAbsolute();
                break;
            case FileBuilder::ATTR_ADDRESS_PATH_RELATIVE;
                $value = $file->getPathRelative();
                break;
            case self::ATTR_ADDRESS_MTIME:
                $value = TimestampDataType::cast('@' . $file->getMTime());
                break;
            case self::ATTR_ADDRESS_CTIME:
                $value = TimestampDataType::cast('@' . $file->getCTime());
                break;
            case self::ATTR_ADDRESS_MIMETYPE:
                $value = $file->getMimetype();
                break;
            case self::ATTR_ADDRESS_CONTENT:
                $value = $file->isFile() ? $file->openFile()->read() : null;
                break;
            case self::ATTR_ADDRESS_EXTENSION:
                $value = $file->getExtension();
                break;
            case self::ATTR_ADDRESS_SIZE:
                $value = $file->getSize();
                break;
            case self::ATTR_ADDRESS_IS_FILE:
                $value = $file->isFile();
                break;
            case self::ATTR_ADDRESS_IS_FOLDER:
                $value = $file->isDir();
                break;
            case self::ATTR_ADDRESS_IS_LINK:
                $value = $file->isLink();
                break;
            case self::ATTR_ADDRESS_IS_READABLE:
                $value = $file->isReadable();
                break;
            case self::ATTR_ADDRESS_IS_WRITABLE:
                $value = $file->isWritable();
                break;
        }
        
        // complex properties
        switch (true) {
            case substr($fieldLC, 0, 4) === self::ATTR_ADDRESS_LINE:
                if (! $file->isFile()) {
                    throw new QueryBuilderException('Cannot read line from "' . $file->getPathRelative() . '" - it is not a file!');
                }
                $lineNo = intval(trim(substr($fieldLC, 4), '()'));
                if (! ($lineNo > 0)) {
                    throw new QueryBuilderException('Cannot read line "' . $lineNo . '" from file! Invalid line number.');
                }
                $value = $file->openFile()->readLine($lineNo);
                break;
            case substr($fieldLC, 0, 7) === self::ATTR_ADDRESS_SUBPATH:
                list($start, $length) = explode(',', trim(substr($fieldLC, 7), '()'));
                $start = trim($start);
                $length = trim($length);
                if (! is_numeric($start) || ($length !== null && ! is_numeric($length))) {
                    throw new QueryBuilderException('Cannot query "' . $$dataAddress . '" on file path "' . $file->getPathname() . '": invalid start or length condition!');
                }
                $pathParts = explode($file->getDirectorySeparator(), $file->getFolderPath());
                $subParts = array_slice($pathParts, $start, $length);
                $value = implode($file->getDirectorySeparator(), $subParts);
                break;
        }
        
        return $value;
    }
    
    /**
     * The FileBuilder can only handle attributes FILE objects, so no relations to
     * other objects than those based on exface.Core.FILE can be read directly.
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::canReadAttribute()
     */
    public function canReadAttribute(MetaAttributeInterface $attribute) : bool
    {
        if ($attribute->getRelationPath()->isEmpty()) {
            return true;
        }
        
        foreach ($attribute->getRelationPath()->getRelations() as $rel) {
            if (! $rel->getRightObject()->is('exface.Core.FILE')) {
                return false;
            }
        }
        
        return true;
    }
    
    protected function getFolderDepth() : ?int
    {
        return $this->getMainObject()->getDataAddressProperty(FileBuilder::DAP_FOLDER_DEPTH);
    }
}