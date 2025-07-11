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
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\MimeTypeDataType;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\RegularExpressionDataType;

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
 * - `~file:subpath(start,length)` - extracts a subset of the relative folder path (excl. the filename): e.g.
 * `subpath(0,2)` from the path `exface/Core/Translations/Objects` would yield `exface/Core`,
 * while `subpath(0,-1)` would produce `exface/Core/Translations`, `subpath(2)` - `Translations/Objects`
 * and `subpath(-1)` - `Objects`
 * - `~file:is_file`
 * - `~file:is_folder`
 * - `~file:is_link`
 * - `~folder:~folder:path_relative`
 * - `~file:line(n)` - n-th line of the file starting with 1: e.g. `line(1)` to get the first line. This only works with files!
 * - `~file:extract(/^Feature: (.*)$/im, 1)` - the first match for the given regular expression in the file. The
 * second argument specifies the index of the matching group (i.e. `(.*)` in the example) within the pattern.
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
    
    /**
     * Use `/` or `\` as directory separator in file system paths - `/` by default.
     *
     * @uxon-property directory_separator
     * @uxon-target object
     * @uxon-type [/,\]
     * @uxon-default /
     */
    const DAP_DIRECTORY_SEPARATOR = 'directory_separator';
    
    
    const ATTR_ADDRESS_PREFIX_FILE= '~file:';
    
    const ATTR_ADDRESS_PREFIX_FOLDER = '~folder:';
    
    const ATTR_ADDRESS_PATH_ABSOLUTE = 'path_absolute';
    
    const ATTR_ADDRESS_PATH_RELATIVE = 'path_relative';
    
    const ATTR_ADDRESS_LINE = 'line';
    
    const ATTR_ADDRESS_EXTRACT = 'extract';
    
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
    
    const REGEX_DELIMITER = '/';

    private $fullReadRequired = null;
    
    /**
     * 
     * @return FileReadDataQuery
     */
    protected function buildQueryToRead() : FileReadDataQuery
    {
        $query = new FileReadDataQuery($this->getDirectorySeparator());
        
        // Calculate paths and filenames from the current filters
        // TODO instead of working with an array of paths and filename pattersn, create
        // a common method from filling a query with filters
        // - full path with filename, but without wildcards -> addFilePath()
        // - filename -> addFilenamePattern()
        // - folder path -> addFolder()
        // - paths with wildcards -> split into filenames and folders
        // The current solution has trouble distinguishing between folder and file paths!
        // Cases known to produce problems:
        // - path and filename separately without wildcards - e.g. from DataSourceFileInfo
        // - full path with filename (not separated into two filters)
        // - folder with file name pattern (e.g. data address of `exface.Core.BEHAVIOR`)
        // Perhaps we also need to normalize folder paths and enforce a trailing slash?
        $pathPatterns = $this->buildPathPatternFromFilterGroup($this->getFilters(), $query);
        $filenamePattern = $this->buildFilenameFromFilterGroup($this->getFilters(), $query);
        $filenameFilters = [];
        $folderPatterns = [];
        // If there is a filename, add it to the query
        if ($filenamePattern === '' || $filenamePattern === '*' || $filenamePattern === '*.*') {
            $filenamePattern = null;
        }
        
        // Now add each path
        foreach ($pathPatterns as $path) {
            if ($path === null || $path === '') {
                $path = $this->getPathForObject($this->getMainObject()) ?? '';
            }
            
            // The end of the path might contain a filename or mask too: e.g. the data
            // address of exface.Core.BEHAVIOR is `/*/*/Behaviors/*.php`.
            $pathEnd = FilePathDataType::findFileName($path, true);
            $folder = mb_substr($path, 0, (-1) * mb_strlen($pathEnd));
            if ($pathEnd === '') {
                $pathEnd = null;
            }
            $pathEndIsFile = $pathEnd !== null && mb_strpos($pathEnd, '.') !== false;
            $pathEndIsPattern = $pathEnd !== null && FilePathDataType::isPattern($pathEnd);
            switch (true) {
                case $pathEndIsFile === true && ! FilePathDataType::isPattern($path):
                    $query->addFilePath($path);
                    break;
                case $pathEndIsFile === true:
                case $pathEndIsPattern === true:
                    $filenameFilters[] = $pathEnd;
                    $folderPatterns[] = $folder;
                    break;
                // In case the path includes no file at all, assume it to be a folder
                default:
                    $folderPatterns[] = $path;
                    break;
            }
        }

        if ($filenamePattern === null && count($filenameFilters) === 1) {
            $filenamePattern = $filenameFilters[0];
            $filenameFilters = [];
        }
        
        foreach ($folderPatterns as $folder) {
            $query->addFolder($folder);
        }
        
        if ($filenamePattern !== null) {
            $query->addFilenamePattern($filenamePattern);
        }
        
        if (! empty($filenameFilters)) {
            $query->addFilter(function(FileInfoInterface $fileInfo) use ($filenameFilters) {
                $filename = $fileInfo->getFilename(true);
                foreach ($filenameFilters as $pattern) {
                    switch (true) {
                        case RegularExpressionDataType::isRegex($pattern):
                            $match = preg_match($pattern, $filename) === 1;
                            break;
                        case FilePathDataType::isPattern($pattern):
                            $match = FilePathDataType::matchesPattern($filename, $pattern);
                            break;
                        default:
                            $match = strcasecmp($pattern, $filename) === 0;
                            break;
                    }
                    if ($match === true) {
                        return true;
                    }
                }
                return false;
            }, 'filename must match any of ["' . implode('", "', $filenameFilters) . '"]');
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
    
    /**
     * 
     * @param QueryPartAttribute $qpart
     * @return bool
     */
    protected function isFilename(QueryPartAttribute $qpart) : bool
    {
        $addr = mb_strtolower(trim($qpart->getDataAddress()));
        return $addr === FileBuilder::ATTR_ADDRESS_PREFIX_FILE . FileBuilder::ATTR_ADDRESS_NAME 
        || $addr === FileBuilder::ATTR_ADDRESS_PREFIX_FILE . FileBuilder::ATTR_ADDRESS_NAME_WITHOUT_EXTENSION 
        || $addr === 'name' 
        || $addr === 'filename';
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
        return $addr === FileBuilder::ATTR_ADDRESS_PREFIX_FILE . FileBuilder::ATTR_ADDRESS_CONTENT 
        || $addr === 'contents';
    }

    /**
     * Returns TRUE if give query part references the folder path (absolute or relative) and FASE otherwise
     *
     * @param QueryPartAttribute $qpart
     * @return bool
     */
    protected function isFolderPath(QueryPartAttribute $qpart) : bool
    {
        $addr = mb_strtolower(trim($qpart->getDataAddress()));
        return $addr === FileBuilder::ATTR_ADDRESS_PREFIX_FOLDER . FileBuilder::ATTR_ADDRESS_PATH_ABSOLUTE 
        || $addr === FileBuilder::ATTR_ADDRESS_PREFIX_FOLDER . FileBuilder::ATTR_ADDRESS_PATH_RELATIVE;
    }
    
    /**
     * 
     * @param QueryPartAttribute $qpart
     * @return bool
     */
    protected function isFilePath(QueryPartAttribute $qpart) : bool
    {
        $addr = mb_strtolower($qpart->getDataAddress());
        return $this->isFilePathAddress($addr);
    }
    
    /**
     * 
     * @param string $addr
     * @return bool
     */
    protected function isFilePathAddress(string $addr) : bool
    {
        return $addr === self::ATTR_ADDRESS_PREFIX_FILE . self::ATTR_ADDRESS_PATH_RELATIVE 
        || $addr === self::ATTR_ADDRESS_PREFIX_FILE . self::ATTR_ADDRESS_PATH_ABSOLUTE
        // Backwards compatibility with legacy data addresses
        || $addr === '~filepath'
        || $addr === 'pathname_absolute'
        || $addr === '~filepath_relative'
        || $addr === 'pathname_relative';
    }
    
    /**
     *
     * @param string $addr
     * @return bool
     */
    protected function isFolderPathAddress(string $addr) : bool
    {
        return $addr === self::ATTR_ADDRESS_PREFIX_FOLDER . self::ATTR_ADDRESS_PATH_RELATIVE
        || $addr === self::ATTR_ADDRESS_PREFIX_FOLDER . self::ATTR_ADDRESS_PATH_ABSOLUTE;
    }
    
    /**
     *
     * @param string $dataAddress
     * @return bool
     */
    protected function isFileProperty(QueryPartAttribute $qpart) : bool
    {
        $prop = mb_strtoupper(trim($qpart->getDataAddress()));
        $begin = substr($prop, 0, 1);
        if ($begin === '_' || $begin === '~') {
            if (defined(__CLASS__ . '::ATTR_ADDRESS_' . substr($prop, 1))) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * @param QueryPartFilterGroup $qpart
     * @param FileReadDataQuery $query
     * @return string|NULL
     */
    protected function buildFilenameFromFilterGroup(QueryPartFilterGroup $qpart, FileReadDataQuery $query) : ?string
    {
        $values = [];
        $filtersApplied = [];
        $filename = null;
        $pregDelim = self::REGEX_DELIMITER;
        foreach ($qpart->getFilters() as $filter) {
            if ($this->isFilename($filter)) {
                switch ($filter->getComparator()) {
                    case ComparatorDataType::EQUALS:
                    case ComparatorDataType::IS:
                        $mask = preg_quote($filter->getCompareValue(), $pregDelim);
                        if ($filter->getComparator() === ComparatorDataType::EQUALS) {
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
                    $filename = "$pregDelim(" . implode('|', $values) . "){$pregDelim}i";
                    break;
                case EXF_LOGICAL_AND: 
                    if (count($values) === 1) {
                        $filename = $pregDelim . $values[0] . $pregDelim . 'i';
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
        $addr = $this->getPathForObject($this->getMainObject()) ?? '';
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
            $filterAddr = $qpart->getDataAddress();
            $filterVal = $qpart->getCompareValue();
            $filterComp = $qpart->getComparator();
            $isPathNameFilter = $this->isFilePathAddress($filterAddr);
            $isFolderFilter = $this->isFolderPathAddress($filterAddr);

            // Calculate folder and filename patters from some other data addresses too
            switch (true) {
                // `~folder:name ==` or `~folder:name =` 
                case $addr === '' && $filterAddr === self::ATTR_ADDRESS_PREFIX_FOLDER . self::ATTR_ADDRESS_NAME:
                    $isFolderFilter = true;
                    if ($filterComp === ComparatorDataType::EQUALS) {
                        $filterVal = "*/{$filterVal}";
                    } elseif ($filterComp === ComparatorDataType::IS) {
                        $filterVal = "*/*{$filterVal}*";
                    }
                    break;
            }

            if ($isPathNameFilter || $isFolderFilter || in_array($qpart->getAlias(), $addrPhs)) {
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
                switch ($filterComp) {
                    // If we are searching for the exact value of a path or filename, we can use the value
                    // in our paths in most cases.
                    // NOTE: this only works for EQUALS comparators. IS does not work, because it would produce
                    // a pattern and that pattern would conflict with possible patterns in the data address of
                    // the object.
                    case ComparatorDataType::EQUALS:
                        //if attribute alias is a placeholder in the path patterns, replace it with the value
                        if (in_array($qpart->getAlias(), $addrPhs)) {                            
                            $addrPhsValues[$qpart->getAlias()] = $filterVal;
                            foreach ($pathPatterns as $i => $pattern) {
                                $pathPatterns[$i] = Filemanager::pathNormalize(StringDataType::replacePlaceholders($pattern, $addrPhsValues, false));
                            }
                        } else {
                            $filterVal = Filemanager::pathNormalize($filterVal);
                            if ($isPathNameFilter) {
                                $uidPaths[] = $filterVal;
                            }
                            if ($isFolderFilter) {
                                $pathPatterns[] = $filterVal;
                            }
                        }
                        break;
                    case ComparatorDataType::IN:
                        $values = explode($qpart->getValueListDelimiter(), $filterVal);
                        //if attribute alias is a placeholder in the path patterns, replace it with the values (therefore creating more pattern entries)
                        if (in_array($qpart->getAlias(), $addrPhs)) {
                            foreach ($values as $val) {
                                $addrPhsValues[$qpart->getAlias()] = trim($val);
                                foreach ($pathPatterns as $i => $pattern) {
                                    $pathPatterns[$i] = Filemanager::pathNormalize(StringDataType::replacePlaceholders($pattern, $addrPhsValues, false));
                                }
                            }
                        } else {
                            foreach ($values as $val) {
                                $val = trim($val);
                                if ($isPathNameFilter) {
                                    $uidPaths[] = Filemanager::pathNormalize($val);
                                }
                                if ($isFolderFilter) {
                                    $pathPatterns[] = Filemanager::pathNormalize($val);
                                }
                            }
                        }
                        break;
                    default:
                        $qpart->setApplyAfterReading(true);
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
                $this->addAttribute($qpart->getExpression()->__toString());
                $qpart->setApplyAfterReading(true);
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
                        throw new QueryBuilderException('Cannot resolve AND-filter over filename and path patterns both at the same time: "' . $pathPattern . '" and "' . $uidPattern . '"');
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
        $pathArray = $this->buildPathsFromValues();
        if (empty($pathArray)) {
            throw new QueryBuilderException('Cannot create files: no paths specified!');
        }
        foreach ($pathArray as $path) {
            if ($path === null || $path === '') {
                throw new QueryBuilderException('Cannot create file: path is empty!');
            }
        }
        $contentQparts = $this->getValuesForFileContent();
        
        $query = new FileWriteDataQuery($this->getDirectorySeparator());
        switch (true) {
            case count($contentQparts) === 1:
                $contentArray = $this->buildFilesContentsFromValues(reset($contentQparts));
                if (count($pathArray) !== count($contentArray)) {
                    throw new QueryBuilderException('Cannot update files: only ' . count($contentArray) . ' of ' . count($pathArray) . ' files exist!');
                }
                foreach ($pathArray as $i => $path) {
                    // Cannot save anything to an empty path. This is not a real error though
                    // - it may happen if multiple rows are saved and some do not really need
                    // a file to be created
                    if ($path === null) {
                        continue;
                    }
                    
                    $content = $contentArray[$i];
                    // See if empty content is feasable for the expected mime type!
                    // E.g. empty text files are OK, but an empty jpeg cannot be correct.
                    if (empty($content)) {
                        $ext = FilePathDataType::findExtension($path);
                        if ($ext) {
                            $type = MimeTypeDataType::guessMimeTypeOfExtension($ext);
                            if (MimeTypeDataType::isBinary($type)) {
                                throw new QueryBuilderException('Cannot create empty file "' . $path . '" of type "' . $type . '" - files of this type may not be empty!');
                            }
                        }
                    }
                    $query->addFileToSave($path, $content);
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
        
        $performed = $dataConnection->query($query);
        return new DataQueryResultData([], $performed->countAffectedRows());
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
                break;
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
     * 
     * @param string $basePath
     * @return string[]
     */
    protected function buildPathsFromValues() : array
    {
        $pathQpart = null;
        $filenameQpart = null;
        $contentQpart = null;
        $folderQpart = null;
        $uidIsPath = $this->isFilePathAddress($this->getMainObject()->getUidAttribute()->getDataAddress());
        foreach ($this->getValues() as $qpart) {
            if ($qpart->hasUids() && $uidIsPath === true) {
                return $qpart->getUids();
            }
            switch (true) {
                case $this->isFilePath($qpart) && $qpart->hasValues():
                    $pathQpart = $qpart;
                    break;
                case $this->isFilename($qpart) && $qpart->hasValues():
                    $filenameQpart = $qpart;
                    break;
                case $this->isFileContent($qpart) && $qpart->hasUids():
                    $contentQpart = $qpart;
                    break;
                case $this->isFolderPath($qpart) && $qpart->hasValues():
                    $folderQpart = $qpart;
                    break;
            }
        }
        
        $objectBasePath = $this->getPathForObject($this->getMainObject());
        $objectBasePath = rtrim(trim($objectBasePath), '*?');
        $paths = [];
        switch (true) {
            case $contentQpart !== null && $uidIsPath === true:
                $paths = $contentQpart->getUids();
                break;
            case $pathQpart !== null:
                $paths = $pathQpart->getValues();
                break;
            case $folderQpart !== null && $filenameQpart !== null:
                foreach ($folderQpart->getValues() as $i => $folder) {
                    $paths[$i] = $folder . $this->getDirectorySeparator() . $filenameQpart->getValues()[$i];
                }
                break;
            case $filenameQpart !== null && ! FilePathDataType::isPattern($objectBasePath):
                $paths = [];
                $sep = $this->getDirectorySeparator();
                $addr = FilePathDataType::normalize($this->getPathForObject($this->getMainObject()) ?? '', $sep);
                $addr = StringDataType::substringBefore($addr, $sep, $addr, false, true);
                $addrPhs = StringDataType::findPlaceholders($addr);
                
                foreach ($filenameQpart->getValues() as $rowIdx => $filename) {
                    $phVals = [];
                    foreach ($addrPhs as $ph) {
                        if ($phQpart = $this->getValue($ph)) {
                            $phVals[$ph] = $phQpart->getValues()[$rowIdx];
                        }
                    }
                    $path = StringDataType::replacePlaceholders($addr, $phVals) . '/' . $filename;
                    $paths[$rowIdx] = $path;
                }
                break;
        }

        return $paths;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::read()
     */
    public function read(DataConnectionInterface $dataConnection) : DataQueryResultDataInterface
    {
        $result_rows = [];
        $pagination_applied = false;
        // Check if force filtering is enabled
        if ($this->getMainObject()->getDataAddressProperty(FileBuilder::DAP_FORCE_FILTERING) && count($this->getFilters()->getFiltersAndNestedGroups()) < 1) {
            return new DataQueryResultData([], 0);
        }
        
        $query = $this->buildQueryToRead();
        $performedQuery = $dataConnection->query($query);
        $rowsTotal = 0;
        // FIXME how to make sure we only read ALL files if a total count is required?
        // When reading, we don't know, if we will need the total count, it is fetched
        // separately via count() method. We could 
        // $fullScan = $query->isFullScanRequired;
        // Full scan means, all FileInfo objects need to be instantiated and loaded.
        // This is required, if we need to know, how many files there are in total or
        // if we need all the data for postprocessing.
        $fullScan = true;
        // Full read means, we not only need a full scan, but also need to fill all result
        // rows by reading file properties and maybe even contents. This is the case if we
        // need to filter result data, sort it or do any other in-memory operations.
        // If we do not need a full read, we can avoid processing FileInfo data for rows,
        // that are outside of the pagination window.
        $fullRead = $this->isFullReadRequired($query);
        $limit = $this->getLimit();
        $offset = $this->getOffset() ?? 0;
        foreach ($performedQuery->getFiles() as $fileInfo) {
            $rowsTotal++;
            $limitReached = $limit > 0 && $rowsTotal > ($offset + $limit);
            // Skip rows, that are positioned below the offset if we neither need a count nor post-processing
            if ($rowsTotal <= $offset) {
                if ($fullScan === false && $fullRead === false) {
                    continue;
                }
            }
            // Skip rest if we are over the limit unless we need that rest for post-processing
            if ($limitReached === true && $fullRead === false) {
                // If no full scan is required, apply pagination right away, so we do not even need to reed the files not being shown
                if ($fullScan === false) {
                    break;
                }
            } else {
                $result_rows = array_merge($result_rows, $this->buildResultRows($fileInfo));
            }
        }
        $paginationApplied = $fullRead === false;

        $result_rows = $this->applyFilters($result_rows);
        $totalCount = count($result_rows) + $offset;
        if ($rowsTotal > $totalCount && ! $fullRead) {
            $totalCount = $rowsTotal;
        }
        $result_rows = $this->applySorting($result_rows);
        $result_rows = $this->applyAggregations($result_rows, $this->getAggregations());
        if (! $paginationApplied) {
            $result_rows = $this->applyPagination($result_rows);
        }
        $rowCount = count($result_rows);
        
        return new DataQueryResultData($result_rows, $rowCount, ($totalCount > $rowCount), $totalCount);
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
        // Update by path (in one of the values)
        $pathArray = $this->buildPathsFromValues();
        
        // Update by filters
        if (empty($pathArray) && ! $this->getFilters()->isEmpty()) {
            // Read all files using the filters and use their UIDs to delete them
            // one-by-one
            $fileQuery = new FileBuilder($this->getSelector());
            $fileQuery->setMainObject($this->getMainObject());
            $fileQuery->setFilters($this->getFilters());
            $uidQpart = $fileQuery->addAttribute($this->getMainObject()->getUidAttributeAlias());
            // Read all path attributes because the filters may need to be applied after reading,
            // so instead of trying to figure out which attribute will be needed, we just add 
            // them all here.
            foreach ($this->findPathAttributes($this->getMainObject()) as $attr) {
                // The UID has been alread added
                if ($attr->isUidForObject()) {
                    continue;
                }
                $fileQuery->addAttribute($attr->getAliasWithRelationPath());
            }
            $fileReadResult = $fileQuery->read($dataConnection);
            foreach ($fileReadResult->getResultRows() as $row) {
                $pathArray[] = $row[$uidQpart->getColumnKey()];
                if (count($pathArray) > 1) {
                    throw new QueryBuilderException('Cannot update more than 1 file at a time by filters!');   
                }
            }
        }
        
        // Do the updating
        $query = new FileWriteDataQuery($this->getDirectorySeparator());
        if (empty($pathArray) === false) {
            $contentQparts = $this->getValuesForFileContent();
            switch (true) {
                case count($contentQparts) === 1:
                    $contentArray = $this->buildFilesContentsFromValues(reset($contentQparts));
                    if (count($pathArray) !== count($contentArray)) {
                        throw new QueryBuilderException('Cannot update files: only ' . count($contentArray) . ' of ' . count($pathArray) . ' files exist!');
                    }
                    foreach ($pathArray as $i => $path) {
                        $content = $contentArray[$i];
                        // Skip rows with content `NULL` because these would be the updates,
                        // where the content is not to be changed!
                        if ($path !== null && $content !== null) {
                            $query->addFileToSave($path, $content);
                        }
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
        
        $performed = $dataConnection->query($query);
        return new DataQueryResultData([], $performed->countAffectedRows());
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return MetaAttributeInterface[]
     */
    protected function findPathAttributes(MetaObjectInterface $object) : array
    {
        $attrs = [];
        foreach ($object->getAttributes() as $attr) {
            if ($this->isFilePathAddress($attr->getDataAddress())) {
                $attrs[] = $attr;
            }
        }
        return $attrs;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::delete()
     */
    public function delete(DataConnectionInterface $dataConnection) : DataQueryResultDataInterface
    {
        $query = new FileWriteDataQuery($this->getDirectorySeparator());
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
    protected function buildResultRows(FileInfoInterface $file) : array
    {
        $row = [];
        foreach ($this->getAttributes() as $qpart) {
            if ($field = $qpart->getAttribute()->getDataAddress()) {
                $row[$qpart->getColumnKey()] = $this->buildResultValueFromFile($file, $field);
            }
        }
        return [$row];
    }
    
    /**
     * 
     * @param FileInfoInterface $file
     * @param string $dataAddress
     * 
     * @throws QueryBuilderException
     * 
     * @return mixed
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
            // Load folder properties from the ~folder: via `getFolderInfo()` except
            // for those, that can be determined from the path directly. DO NOT call
            // `getFolderInfo()` for these properties as this will read the folder and
            // might fail if it is a virtual folder, the user has not general access
            // to it, etc. These failures might prevent reading files inside the folder.
            switch ($fieldLC) {
                case '~folder:path':
                    return $file->getFolderPath();
                case '~folder:name':
                    return $file->getFolderName();
                default:                    
                    $folderAddr = substr($fieldLC, strlen(self::ATTR_ADDRESS_PREFIX_FOLDER));
                    $folderAddr = strpos($folderAddr, ':') === false ? self::ATTR_ADDRESS_PREFIX_FILE . $folderAddr : $folderAddr;
                    $folderInfo = $file->getFolderInfo();
                    return $folderInfo === null ? null : $this->buildResultValueFromFile($folderInfo, $folderAddr);
            }
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
                $value = $file->getMTime() === null ? null : TimestampDataType::cast('@' . $file->getMTime());
                break;
            case self::ATTR_ADDRESS_CTIME:
                $value = $file->getCTime() === null ? null : TimestampDataType::cast('@' . $file->getCTime());
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
            case StringDataType::startsWith($fieldLC, self::ATTR_ADDRESS_EXTRACT . '('):
                if (! $file->isFile()) {
                    throw new QueryBuilderException('Cannot read line from "' . $file->getPathRelative() . '" - it is not a file!');
                }
                $options = mb_substr(StringDataType::substringAfter($fieldLC, '('), 0, -1);
                $lastComma = strrpos($options, ',');
                $idx = intval(trim(mb_substr($options, $lastComma+1)));
                $regex = trim(mb_substr($options, 0, $lastComma));
                if (! RegularExpressionDataType::isRegex($regex)) {
                    throw new QueryBuilderException('Cannot read extract from file! Invalid regular expression "' . $regex . '"');
                }
                $contents = $file->openFile()->read();
                $matches = [];
                preg_match($regex, $contents, $matches);
                $value = $matches[$idx];
                break;
            case substr($fieldLC, 0, 7) === self::ATTR_ADDRESS_SUBPATH:
                list($start, $length) = explode(',', trim(substr($fieldLC, 7), '()'));
                $start = trim($start);
                $length = trim($length);
                if (! is_numeric($start) || ($length !== null && ! is_numeric($length))) {
                    throw new QueryBuilderException('Cannot query "' . $dataAddress . '" on file path "' . $file->__tostring() . '": invalid start or length condition!');
                }
                $pathParts = explode($file->getDirectorySeparator(), $file->getFolderInfo()->getPathRelative());
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
        
        // Can't read related attribute if any object in the relation path is not
        // the same as this object (i.e. is either not a file or is a different type of file)
        $thisObj = $this->getMainObject();
        foreach ($attribute->getRelationPath()->getRelations() as $rel) {
            if ($thisObj !== $rel->getRightObject()) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 
     * @return int|NULL
     */
    protected function getFolderDepth() : ?int
    {
        $obj = $this->getMainObject();
        return $obj->getDataAddressProperty(FileBuilder::DAP_FOLDER_DEPTH) ?? $this->getMainObject()->getDataAddressProperty('finder_depth');
    }
    
    /**
     * 
     * @return string
     */
    protected function getDirectorySeparator() : string
    {
        return $this->getMainObject()->getDataAddressProperty(FileBuilder::DAP_DIRECTORY_SEPARATOR) ?? '/';
    }

    /**
     *
     * @param boolean $trueOrFalse
     * @return FileBuilder
     */
    protected function setFullScanRequired(bool $trueOrFalse) : FileBuilder
    {
        $this->fullReadRequired = $trueOrFalse;
        return $this;
    }

    /**
     * Returns TRUE if all data must be read to perform the givn query
     * 
     * Since file queries are based on generators, it is important to know, if a query needs to really load all
     * data (execute all generators) or can stop at some point. 
     * 
     * @param FileReadDataQuery $query
     * @return boolean
     */
    protected function isFullReadRequired(FileReadDataQuery $query) : bool
    {
        if ($this->fullReadRequired === true) {
            return true;
        }
        if ($query->isFullScanRequired() === true) {
            return true;
        }
        foreach ($this->getFilters() as $qpart) {
            if ($qpart->getApplyAfterReading() === true) { 
                return true;
            }
        }
        foreach ($this->getSorters() as $qpart) {
            if ($qpart->getApplyAfterReading() === true) { 
                return true;
            }
        }
        if (! empty($this->getAggregations())) {
            return true;
        }
        return false;
    }

    /**
     * Returns the path or pattern stored in the data address of the given meta object.
     * 
     * Override this method to support data addresses with additions - e.g. the ExcelBuilder
     * supports Worksheet names in its data addresses.
     * 
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $object
     * @return string|null
     */
    protected function getPathForObject(MetaObjectInterface $object) : ?string
    {
        return $this->getMainObject()->getDataAddress();
    }

    /**
     * Returns TRUE if the given address is a file property and can be handled by this query builder
     * 
     * Use this method in extending classes to find out, if an address is a basic file property -
     * see ExcelBuilder for an example.
     * 
     * @param string $address
     * @return bool
     */
    protected function isFileDataAddress(string $address) : bool
    {
        $address = mb_strtolower($address);
        switch (true) {
            case strpos($address, '~file:') === 0:
            case strpos($address, '~folder:') === 0:
            // Still supported legacy data addresses
            case $address === '~folder':
            case $address === '~extension':
            case $address === '~filename_without_extension':
            case $address === '~filename':
            case $address === '~contents':
            case $address === '~filepath_relative':
            case $address === '~filepath':
                return true;
        }
        return false;
    }
}