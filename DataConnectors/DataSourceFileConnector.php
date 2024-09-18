<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use exface\Core\CommonLogic\DataQueries\FileReadDataQuery;
use exface\Core\CommonLogic\DataQueries\FileWriteDataQuery;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\CommonLogic\Filesystem\DataSourceFileInfo;

/**
 * Allows to access files in any data source as if it was a file system if the meta objects have the FileBehavior
 * 
 * This allows to access files stored in data bases using file-oriented query builders like
 * `FileBuilder`, `ExcelBuilder`, etc.
 * 
 * Technically, this connector interprets path schemes like `metamodel://my.app.ObjectAlias/uid_of_file`
 * and loads and passes a custom `splFileInfo` implementation back to the data query. This allows
 * query builders to work with the data as if it was a file.
 * 
 * **REQUIRES** the addressed objects to have `FileBehavior` or `FileAttachmentBehavior`.
 * 
 * @see DataSourceFileInfo
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSourceFileConnector extends TransparentConnector
{
    private $error_if_file_not_found = true;

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     * 
     * @param \exface\Core\Interfaces\DataSources\DataQueryInterface $query
     * @throws \exface\Core\Exceptions\DataSources\DataQueryFailedError
     * @return FileReadDataQuery|FileWriteDataQuery|FileContentsDataQuery
     */
    protected function performQuery(DataQueryInterface $query)
    {
        switch (true) {
            case $query instanceof FileReadDataQuery:
                $query = $this->performRead($query);
                break;
            case $query instanceof FileWriteDataQuery:
                $query = $this->performWrite($query);
                break;
            case $query instanceof FileContentsDataQuery:
                $query = $this->performReadLegacy($query);
                break;
            default:
                throw new DataQueryFailedError($query, 'Invalid query type for connection "' . $this->getAliasWithNamespace() . '": only file queries allowed!');

        }
        return $query;
    }

    protected function performReadLegacy(FileContentsDataQuery $query) : FileContentsDataQuery
    {
        // Check if the file exists and add the splFileInfo and contents resolver to the query
        $fileInfo = new \exface\Core\CommonLogic\DataQueries\DataSourceFileInfo($query->getPathAbsolute(), $this->getWorkbench());
        if ($fileInfo->isExisting()) {
            $query->setFileInfo($fileInfo);
            $query->setFileContents(function(FileContentsDataQuery $query) {
                return $query->getFileInfo()->getContents(); 
            });
        } else {
            $query->setFileExists(false);
            if ($this->isErrorIfFileNotFound()) {
                throw new DataQueryFailedError($query, 'File "' . $query->getPathAbsolute() . '" not found!');
            }
        }
        return $query;
    }

    protected function performRead(FileReadDataQuery $query) : FileReadDataQuery
    {
        $paths = $query->getFolders(true);
        $explicitFiles = $query->getFilePaths(true);

        try {
            return $query->withResult($this->createGenerator(array_merge($paths, $explicitFiles)));
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
    /**
     * 
     * @param array $paths
     * @return \Generator
     */
    protected function createGenerator(array $paths) : \Generator
    {
        foreach ($paths as $file) {
            yield new DataSourceFileInfo($file, $this->getWorkbench());
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
        $filesToSave = $query->getFilesToSave(true);
        $errors = $this->validateFileIntegrityArray($filesToSave);

        $this->tryBeginWriting($errors);
        // Save files
        foreach ($filesToSave as $path => $content) {
            $fileInfo = new DataSourceFileInfo($path, $this->getWorkbench());
            $fileInfo->openFile()->write($content);
            $resultFiles[] = $fileInfo;
        }
        $this->tryFinishWriting($errors);
        
        // Delete files
        foreach ($query->getFilesToDelete(true) as $pathOrInfo) {
            if ($pathOrInfo instanceof FileInfoInterface) {
                $fileInfo = $pathOrInfo;
            } else {
                $fileInfo = new DataSourceFileInfo($pathOrInfo, $this->getWorkbench());
            }
            $fileInfo->delete();
            $resultFiles[] = $fileInfo;
        }
        
        return $query->withResult($resultFiles);
    }

    /**
     * @inheritDoc
     */
    protected function guessMimeType(string $path, string $data): string
    {
        return (new DataSourceFileInfo($path, $this->getWorkbench()))->getMimetype();
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
     * @return DataSourceFileConnector
     */
    public function setErrorIfFileNotFound(bool $value) : DataSourceFileConnector
    {
        $this->error_if_file_not_found = $value;
        return $this;
    }
}