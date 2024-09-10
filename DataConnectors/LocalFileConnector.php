<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\CommonLogic\DataQueries\FileReadDataQuery;
use exface\Core\CommonLogic\DataQueries\FileWriteDataQuery;
use exface\Core\CommonLogic\Filesystem\LocalFileInfo;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\DataSources\FileDataQueryInterface;
use Symfony\Component\Finder\Finder;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\ArrayDataType;

class LocalFileConnector extends TransparentConnector
{

    private $base_path = null;
    
    private $base_path_absolute = null;

    private $use_vendor_folder_as_base = false;
    
    private $error_if_file_not_found = false;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        return;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     *
     * @param FileDataQueryInterface|FileContentsDataQuery
     * @return FileDataQueryInterface|FileContentsDataQuery
     */
    protected function performQuery(DataQueryInterface $query)
    {
        switch (true) {
            case $query instanceof FileDataQueryInterface:
                break;
            // Transform legacy FileContentsDataQuery for backwards compatibility
            case $query instanceof FileContentsDataQuery:
                $fileContentsQuery = $query;
                $query = new FileReadDataQuery();
                if (null !== $val = $fileContentsQuery->getBasePath()) {
                    $query->setBasePath($val);
                }
                if (null !== $val = $fileContentsQuery->getTimeZone()) {
                    $query->setTimeZone($val);
                }
                if (null !== $val = $fileContentsQuery->getPathRelative()) {
                    $query->addFilePath($val);
                }
                if (null !== $val = $fileContentsQuery->getPathAbsolute()) {
                    $query->addFilePath($val);
                }
                break;
            default:
                throw new DataConnectionQueryTypeError($this, 'DataConnector "' . $this->getAliasWithNamespace() . '" expects an instance of FileDataQueryInterface as query, "' . get_class($query) . '" given instead!', '6T5W75J');
        }
        
        // If the query does not have a base path, use the base path of the connection
        $connectionBase = $this->getBasePath();
        if ($connectionBase !== null) {
            $queryBase = $query->getBasePath();
            switch (true) {
                case ! $queryBase:
                    $query->setBasePath($connectionBase);
                    break;
                case FilePathDataType::isAbsolute($queryBase) && FilePathDataType::isAbsolute($connectionBase):
                    if (StringDataType::startsWith($connectionBase, $queryBase)) {
                        $query->setBasePath($connectionBase);
                    } elseif (! StringDataType::startsWith($queryBase, $connectionBase)) {
                        throw new DataQueryFailedError($query, 'Cannot combine base paths of file query ("' . $queryBase .  '") and connection ("' . $connectionBase . '")');
                    }
                    break;
            }
        }
        
        if ($query instanceof FileWriteDataQuery) {
            $queryPerformed = $this->performWrite($query);
        } else {
            $queryPerformed = $this->performRead($query);
        }

        // If the original query was a legacy FileContentsDataQuery, fill it with information and return it
        if ($fileContentsQuery !== null) {
            foreach ($queryPerformed->getFiles() as $fileInfo) {
                $fileContentsQuery->setFileContents($fileInfo->openFile()->read());
                $fileContentsQuery->setFileExists($fileInfo->exists());
                if ($fileInfo instanceof LocalFileInfo) {
                    $fileContentsQuery->setFileInfo($fileInfo->toSplFileInfo());
                }
                return $fileContentsQuery;
            }
        }

        return $queryPerformed;
    }
    
    /**
     * 
     * @param FileReadDataQuery $query
     * @throws DataQueryFailedError
     * @return FileReadDataQuery
     */
    protected function performRead(FileReadDataQuery $query) : FileReadDataQuery
    {
        $paths = $query->getFolders(true);
        $explicitFiles = $query->getFilePaths(true);
        
        // Prepare an array of absolute paths to search in
        // Note: $query->getBasePath() already includes the base path of this connection
        // - see `performQuery()`
        $basePath = $query->getBasePath();
        
        // If no paths could be found anywhere (= the query object did not have any folders defined), use the base path
        if (empty($paths) && empty($explicitFiles) && $basePath !== '') {
            $paths[] = $basePath;
        }
        
        // Now doublecheck if all explicit (non-wildcard) paths exists because otherwise
        // finder will throw an error. Just remove all non-existant paths as they definitely
        // do not contain files.
        foreach ($paths as $nr => $path){
            if (strpos($path, '*') === false && ! is_dir($path)){
                unset($paths[$nr]);
            }
        }
        
        // If there are no paths at this point, we don't have any existing folder to look in,
        // so add an empty result to the finder and return it. We must call in() or append()
        // to be able to iterate over the finder!
        if (empty($paths) && empty($explicitFiles)){
            return $query->withResult([]);
        }
        
        // Instantiate Symfony Finder
        $finder = new Finder();
        if ($query->getFolderDepth() !== null) {
            $finder->depth($query->getFolderDepth());
        }
        
        $namePatterns = $query->getFilenamePatterns();
        if (! empty($namePatterns)) {
            $finder->name(array_unique($namePatterns));
        }
        
        try {
            // Remove case-insensitive duplicates on windows since file system paths are
            // case insensitive here
            if (DIRECTORY_SEPARATOR === '\\') {
                $paths = ArrayDataType::filterUniqueCaseInsensitive($paths);
            }
            if (! empty($explicitFiles)) {
                $finder->append(array_filter($explicitFiles, 'file_exists'));
            }
            $finder->in($paths);
            return $query->withResult($this->createGenerator($finder, $basePath, $query->getDirectorySeparator(), $explicitFiles));
        } catch (\Exception $e) {
            throw new DataQueryFailedError($query, "Failed to read local files", null, $e);
        }
    }
    
    /**
     * 
     * @param Finder $finder
     * @param string $basePath
     * @param string $directorySeparator
     * @return \Generator
     */
    protected function createGenerator(Finder $finder, string $basePath = null, string $directorySeparator = '/') : \Generator
    {
        foreach ($finder as $file) {
            yield new LocalFileInfo($file, $basePath, $directorySeparator);
        }
    }
    
    /**
     * 
     * @param FileWriteDataQuery $query
     * @throws DataQueryFailedError
     * @return FileWriteDataQuery
     */
    protected function performWrite(FileWriteDataQuery $query) : FileWriteDataQuery
    {
        $resultFiles = [];
        $fm = $this->getWorkbench()->filemanager();
        
        // Save files
        foreach ($query->getFilesToSave(true) as $path => $content) {
            if ($path === null) {
                throw new DataQueryFailedError($query, 'Cannot write file with an empty path!');
            }
            $fm->dumpFile($path, $content ?? '');
            $resultFiles[] = new LocalFileInfo($path);
        }
        
        // Delete files
        $deleteEmptyFolders = $query->getDeleteEmptyFolders();
        // Note: the base path of the query already includes the base path of this connection
        // - see `performQuery()`
        $basePath = $query->getBasePath();
        foreach ($query->getFilesToDelete(true) as $pathOrInfo) {
            if ($pathOrInfo instanceof FileInfoInterface) {
                $path = $pathOrInfo->getPath();
                $fileInfo = $pathOrInfo;
            } else {
                $path = $pathOrInfo;
                $fileInfo = null;
            }
            
            if (! file_exists($path)) {
                continue;
            }
            
            // Do delete now
            if (is_dir($path)) {
                $fm->deleteDir($path);
            } else {
                $check = unlink($path);
                if ($check === false) {
                    throw new DataQueryFailedError($query, 'Cannot delete file "' . $pathOrInfo . '"!');
                }
            }
            
            $resultFiles[] = $fileInfo ?? new LocalFileInfo($path, $basePath, $query->getDirectorySeparator());
            
            if ($deleteEmptyFolders === true) {
                $folder = FilePathDataType::findFolderPath($path);
                if ($folder !== '' && $fm::isDirEmpty($folder)) {
                    $fm::deleteDir($folder);
                }
            }
        }
        
        return $query->withResult($resultFiles);
    }

    /**
     * 
     * @return string|NULL
     */
    public function getBasePath() : ?string
    {
        if ($this->base_path_absolute === null) {
            $fm = $this->getWorkbench()->filemanager();
            switch (true) {
                case $this->getUseVendorFolderAsBase()  === true:
                    $base = $fm->getPathToVendorFolder();
                    if ($this->base_path !== null && $this->base_path !== '') {
                        $base = FilePathDataType::join([$base, $this->base_path]);
                    }
                    break;
                case $this->base_path !== null:
                    $base = $this->base_path;
                    if (! FilePathDataType::isAbsolute($base)) {
                        $base = FilePathDataType::join([$fm->getPathToBaseFolder(), $base]);
                    }
                    break;
                default:
                    $base = $this->getWorkbench()->getInstallationPath();
            }
            $this->base_path_absolute = $base;
        }
        
        return $this->base_path_absolute;
    }

    /**
     * The base path for relative paths in data addresses.
     * 
     * If a base path is defined, all data addresses will be resolved relative to that path.
     *
     * @uxon-property base_path
     * @uxon-type string
     *
     * @param string $value            
     * @return \exface\Core\DataConnectors\LocalFileConnector
     */
    public function setBasePath($value) : LocalFileConnector
    {
        $this->base_path_absolute = null;
        if ($value) {
            $this->base_path = Filemanager::pathNormalize($value, '/');
        } else {
            $this->base_path = '';
        }
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function getUseVendorFolderAsBase() : bool
    {
        return $this->use_vendor_folder_as_base;
    }

    /**
     * Set to TRUE to use the current vendor folder as base path.
     * 
     * All data addresses in this conneciton will then be resolved relative to the vendor folder.
     *
     * @uxon-property use_vendor_folder_as_base
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return \exface\Core\DataConnectors\LocalFileConnector
     */
    public function setUseVendorFolderAsBase(bool $value) : LocalFileConnector
    {
        $this->use_vendor_folder_as_base = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isErrorIfFileNotFound() : bool
    {
        return $this->error_if_file_not_found;
    }
    
    /**
     * Set to TRUE to throw an error if the file was not found instead of returning empty data.
     * 
     * @uxon-property error_if_file_not_found
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return LocalFileConnector
     */
    public function setErrorIfFileNotFound(bool $value) : LocalFileConnector
    {
        $this->error_if_file_not_found = $value;
        return $this;
    }
}
?>