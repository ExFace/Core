<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\DataQueries\FileFinderDataQuery;
use Symfony\Component\Finder\SplFileInfo;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
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

/**
 * Lists files and folders using Symfony Finder Component.
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
 * Attributes are file properties. The following data addresses are available:
 * 
 * - `name` - file/folder name with extension
 * - `folder_name` - name of the containing folder
 * - `path_relative` - folder path relative to the base path of the connector
 * - `pathname_absolute` - absolute file path including extension 
 * - `pathname_relative` - file path including extension relative to the base path 
 * of the connector
 * - `mtime` - last modification time
 * - `ctime` - creation time
 * - `line(n)` - n-th line of the file starting with 1: e.g. `line(1)` to get the first line
 * - `subpath(start,length)` - extracts a subset of the folder path (excl. the filenam): e.g.
 * `subpath(0,2)` from the path `exface/Core/Translations/Objects` would yield `exface/Core`,
 * while `subpath(0,-1)` would produce `exface/Core/Translations`, `subpath(2)` - `Translations/Objects`
 * and `subpath(-1)` - `Objects`
 * - Any getter-methods of the class `SplFileInfo` can be called by using the method 
 * name withoug the get-prefix as data address: e.g. `extension` for `SplFileInfo::getExtension()`
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
 * with one of the selected files being such a file the FileFinder will find also the file in the other folder with that name.
 * This can for example lead to files being accidentally deleted in a folder because they have the same name as a file of another folder
 * but files in the first folder also were selected to delete.
 *
 * 
 * @author Andrej Kabachnik
 *        
 */
class FileFinderBuilder extends AbstractQueryBuilder
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
     * See [depth()](https://symfony.com/doc/current/components/finder.html#directory-depth)
     * method of the Symfony Finder Component. By default the depth is unlimited.
     * Set `finder_depth` to `0` to ignore subfolders completely, `1` will only allow
     * immediate subfolders, etc. Complex expressions like `> 2, < 5` are possible).
     *
     * @uxon-property finder_depth
     * @uxon-target object
     * @uxon-type boolean
     */
    const DAP_FINDER_DEPTH = 'finder_depth';
    
    /**
     * Set to TRUE to delete an empty folder after the last file in it was deleted by this query builder.
     *
     * @uxon-property delete_empty_folders
     * @uxon-target object
     * @uxon-type boolean
     * @uxon-default false
     */
    const DAP_DELETE_EMPTY_FOLDERS = 'delete_empty_folders';
    
    /**
     *
     * @return FileFinderDataQuery
     */
    protected function buildQuery()
    {
        $query = new FileFinderDataQuery();
        
        $path_patterns = $this->buildPathPatternFromFilterGroup($this->getFilters(), $query);
        $filename = $this->buildFilenameFromFilterGroup($this->getFilters(), $query);
        
        // Setup query
        foreach ($path_patterns as $path) {
            if ($path == '') {
                $path = $this->getMainObject()->getDataAddress();
            }
            $last_slash_pos = mb_strripos($path, '/');
            if ($last_slash_pos === false) {
                $path_relative = $path;
            } else {
                $path_relative = substr($path, 0, $last_slash_pos);
                $name = $filename ? $filename : substr($path, ($last_slash_pos + 1));
            }
            if (! is_null($name) && $name !== '') {
                $query->getFinder()->name($name);
            }
            
            $query->addFolder($path_relative);            
        }
        
        if (count($this->getSorters()) > 0) {
            $query->setFullScanRequired(true);
            // All the sorting is done locally
            foreach ($this->getSorters() as $qpart) {
                $qpart->setApplyAfterReading(true);
            }
        }
        
        $depth = $this->getMainObject()->getDataAddressProperty(self::DAP_FINDER_DEPTH);
        if (strpos($depth, ',') !== false) {
            $depth = explode(',', $depth);
        }
        if ($depth !== null) {
            $query->getFinder()->depth($depth);
        }
        
        return $query;
    }
    
    protected function isFilename(QueryPartAttribute $qpart) : bool
    {
        $addr = mb_strtolower($qpart->getDataAddress());
        if ($addr === 'name' || $addr === 'filename') {
            return true;
        }
        return false;
    }
    
    protected function buildFilenameFromFilterGroup(QueryPartFilterGroup $qpart, FileFinderDataQuery $query) : ?string
    {
        $values = [];
        $filtersApplied = [];
        $filename = null;
        foreach ($qpart->getFilters() as $filter) {
            if ($this->isFilename($filter)) {
                switch ($filter->getComparator()) {
                    case EXF_COMPARATOR_EQUALS:
                    case EXF_COMPARATOR_IS:
                        $mask = preg_quote($filter->getCompareValue()) . (mb_strtolower($filter->getDataAddress()) === 'filename' ? '\\.' : '');
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
    
    protected function buildPathPatternFromFilterGroup(QueryPartFilterGroup $qpart, FileFinderDataQuery $query) : array
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
            if ($qpart->getAttribute()->is($this->getMainObject()->getUidAttribute()) || in_array($qpart->getAlias(), $addrPhs)) {
                //add the base data adresse to the patterns if first attribute replacing a placeholder is found
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
                    throw new QueryBuilderException('Other filter operators than "' . EXF_LOGICAL_AND . '" or "'. EXF_LOGICAL_OR . '" are not supported by the FileFinderBuilder');
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
        if ($oper === EXF_LOGICAL_OR) {
            return array_unique(array_merge($pathPatterns, $uidPatterns));
        } elseif (! empty($pathPatterns) && ! empty($uidPatterns)) {
            throw new QueryBuilderException('Can not add multiple different paths from different "' . EXF_LOGICAL_AND .'" combined filters!');
        } elseif (! empty($pathPatterns)) {
            return $pathPatterns;
        } else {
            return $uidPatterns;
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::create()
     */
    public function create(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $fileArray = $this->buildPathsFromValues();
        foreach ($fileArray as $path) {
            $folder = FilePathDataType::findFolderPath($path);
            if (! is_dir($folder)) {
                Filemanager::pathConstruct($folder);
            }
        }
        $contentArray = $this->buildFilesContentsFromValues();
        return new DataQueryResultData([], $this->write($fileArray, $contentArray));
    }
    
    /**
     * 
     * @return string[]
     */
    protected function buildFilesContentsFromValues() : array
    {
        $array = [];
        foreach ($this->getValues() as $qpart) {
            if ($qpart->getDataAddress() === 'contents') {
                $array = $qpart->getValues();
                switch (true) {
                    case $qpart->getDataType() instanceof BinaryDataType && $qpart->getDataType()->getEncoding() === BinaryDataType::ENCODING_BASE64:
                        array_walk($array, 'base64_decode');
                        break;
                    default:
                        foreach ($array as $i => $val) {
                            if (StringDataType::startsWith($val, 'data:', false) && stripos($val, 'base64,') !== false) {
                                $array[$i] = base64_decode(StringDataType::substringAfter($val, 'base64,'));
                            }
                        }
                }
            }
        }
        return $array;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function buildPathsFromValues() : array
    {
        
        switch (true) {
            case $qpart = $this->getValue('PATHNAME_ABSOLUTE'):
                return $qpart->getValues();
            case $qpart = $this->getValue('FILENAME'):
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
                    $paths[] = StringDataType::replacePlaceholders($addr, $phVals) . '/' . $filename;
                }
                return $paths;
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::read()
     */
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $result_rows = array();
        $pagination_applied = false;
        // Check if force filtering is enabled
        if ($this->getMainObject()->getDataAddressProperty(self::DAP_FORCE_FILTERING) && count($this->getFilters()->getFiltersAndNestedGroups()) < 1) {
            return false;
        }
        
        $query = $this->buildQuery();
        if ($files = $data_connection->query($query)->getFinder()) {
            $rownr = - 1;
            $totalCount = count($files);
            foreach ($files as $file) {
                // If no full scan is required, apply pagination right away, so we do not even need to reed the files not being shown
                if (! $query->getFullScanRequired()) {
                    $pagination_applied = true;
                    $rownr ++;
                    // Skip rows, that are positioned below the offset
                    if (! $query->getFullScanRequired() && $rownr < $this->getOffset())
                        continue;
                    // Skip rest if we are over the limit
                    if (! $query->getFullScanRequired() && $this->getLimit() > 0 && $rownr >= $this->getOffset() + $this->getLimit())
                        break;
                }
                // Otherwise add the file data to the result rows
                $result_rows[] = $this->buildResultRow($file, $query);
            }
            $result_rows = $this->applyFilters($result_rows);
            $result_rows = $this->applySorting($result_rows);
            if (! $pagination_applied) {
                $result_rows = $this->applyPagination($result_rows);
            }
        }
        
        if (! $totalCount) {
            $totalCount = count($result_rows);
        }
        
        $rowCount = count($result_rows);
        
        return new DataQueryResultData($result_rows, $rowCount, ($totalCount > $rowCount + $this->getOffset()), $totalCount);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::count()
     */
    public function count(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        return $this->read($data_connection);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::update()
     */
    public function update(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $updatedFileNr = 0;
        
        $query = $this->buildQuery();
        if ($files = $data_connection->query($query)->getFinder()) {
            $fileArray = iterator_to_array($files, false);
            $contentArray = $this->getValue('CONTENTS')->getValues();
            $updatedFileNr = $this->write($fileArray, $contentArray);
        }
        
        return new DataQueryResultData([], $updatedFileNr);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::delete()
     */
    public function delete(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $deletedFileNr = 0;
        $query = $this->buildQuery();
        $deleteEmptyFolder = BooleanDataType::cast($this->getMainObject()->getDataAddressProperty(self::DAP_DELETE_EMPTY_FOLDERS));
        if ($files = $data_connection->query($query)->getFinder()) {
            /* @var \Symfony\Component\Finder\SplFileInfo $file */
            foreach ($files as $file) {
                $folder = $file->getPath();
                unlink($file);
                $deletedFileNr ++;
                if ($deleteEmptyFolder === true && Filemanager::isDirEmpty($folder)) {
                    Filemanager::deleteDir($folder);
                }
            }
        }
        
        return new DataQueryResultData([], $deletedFileNr);
    }

    /**
     * 
     * @param string[] $fileArray
     * @param string[] $contentArray
     * @throws BehaviorRuntimeError
     * @return number
     */
    private function write($fileArray, $contentArray)
    {
        $writtenFileNr = 0;
        if (count($fileArray) !== count($contentArray)) {
            throw new BehaviorRuntimeError($this->getMainObject(), 'The number of passed files doen\'t match the number of passed file contents.');
        }
        
        for ($i = 0; $i < count($fileArray); $i ++) {
            file_put_contents($fileArray[$i], $this->getValue('CONTENTS')->getDataType()->parse($contentArray[$i]));
            $writtenFileNr ++;
        }
        
        return $writtenFileNr;
    }

    protected function buildResultRow(SplFileInfo $file, FileFinderDataQuery $query)
    {
        $row = array();
        
        $file_data = $this->getDataFromFile($file, $query);
        
        foreach ($this->getAttributes() as $qpart) {
            if ($field = strtolower($qpart->getAttribute()->getDataAddress())) {
                switch (true) {
                    case array_key_exists($field, $file_data):
                        $value = $file_data[$field];
                        break;
                    case substr($field, 0, 4) === 'line':
                        $line_nr = intval(trim(substr($field, 4), '()'));
                        if ($line_nr === 1) {
                            $value = $file->openFile()->fgets();
                        } else {
                            $fileObject = $file->openFile();
                            $fileObject->seek(($line_nr-1));
                            $value = $fileObject->current();
                        }
                        break;
                    case substr($field, 0, 7) === 'subpath':
                        list($start, $length) = explode(',', trim(substr($field, 7), '()'));
                        $start = trim($start);
                        $length = trim($length);
                        if (! is_numeric($start) || ($length !== null && ! is_numeric($length))) {
                            throw new QueryBuilderException('Cannot query "' . $field . '" on file path "' . $file->getPathname() . '": invalid start or length condition!');
                        }
                        $pathParts = explode('/', $this->getPathRelative($file->getPath(), $query));
                        $subParts = array_slice($pathParts, $start, $length);
                        $value = implode('/', $subParts);
                        break;
                    /*case 'contents':
                        $value = $file->getContents();
                        if ($qpart->getDataType() instanceof BinaryDataType) {
                            switch ($qpart->getDataType()->getEncoding()) {
                                case BinaryDataType::ENCODING_BASE64:
                                    $value = base64_encode($value);
                                    break;
                            }
                        }
                        break;*/
                    default: 
                        $method_name = 'get' . ucfirst($field);
                        if (method_exists($file, $method_name)) {
                            $value = call_user_func(array(
                                $file,
                                $method_name
                            ));
                        }
                        break;
                }
                $row[$qpart->getColumnKey()] = $value;
            }
        }
        
        return $row;
    }

    protected function getDataFromFile(SplFileInfo $file, FileFinderDataQuery $query)
    {
        $path = Filemanager::pathNormalize($file->getPath());
        $pathname = Filemanager::pathNormalize($file->getPathname());
        $folder_name = StringDataType::substringAfter(rtrim($path, "/"), '/', '', false, true);
        
        $file_data = array(
            'name' => $file->getExtension() ? str_replace('.' . $file->getExtension(), '', $file->getFilename()) : $file->getFilename(),
            'path_relative' => $this->getPathRelative($path, $query, false),
            'pathname_absolute' => $file->getRealPath(),
            'pathname_relative' => $this->getPathRelative($pathname, $query, false),
            'mtime' => TimestampDataType::cast('@' . $file->getMTime()),
            'ctime' => TimestampDataType::cast('@' . $file->getCTime()),
            'folder_name' => $folder_name
        );
        
        return $file_data;
    }
    
    /**
     * Makes $fullPath relative to the query's base path
     * 
     * @param string $fullPath
     * @param FileFinderDataQuery $query
     * @param bool $normalize
     * @param string $normalDirectorySep
     * @return string
     */
    protected function getPathRelative(string $fullPath, FileFinderDataQuery $query, bool $normalize = true, string $normalDirectorySep = '/') : string
    {
        if ($normalize) {
            $fullPath = FilePathDataType::normalize($fullPath, $normalDirectorySep);
        }
        $base_path = $query->getBasePath() ? $query->getBasePath() . $normalDirectorySep : '';
        return $base_path !== '' ? str_replace($base_path, '', $fullPath) : $fullPath;
    }
    
    /**
     * The FileFinderBuilder can only handle attributes of one object - no relations (JOINs) supported!
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::canReadAttribute()
     */
    public function canReadAttribute(MetaAttributeInterface $attribute) : bool
    {
        return $attribute->getRelationPath()->isEmpty();
    }
}
?>