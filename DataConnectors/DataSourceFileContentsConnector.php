<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\CommonLogic\DataQueries\DataSourceFileInfo;

/**
 * DEPRECATED! Allows to access files in any data source as if it was a file system if the meta objects have the FileBehavior
 * 
 * This allows to access files stored in data bases using file-oriented query builders like
 * `FileContentsBuilder`, `ExcelBuilder`, etc.
 * 
 * Technically, this connector interprets path schemes like `metamodel://my.app.ObjectAlias/uid_of_file`
 * and loads and passes a custom `splFileInfo` implementation back to the data query. This allows
 * query builders to work with the data as if it was a file.
 * 
 * **REQUIRES** the addressed objects to have the `FileBehavior`.
 * 
 * @deprecated use DataSourceFileConnector instead!
 * 
 * @see DataSourceFileInfo
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSourceFileContentsConnector extends TransparentConnector
{
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     * @return \SplFileInfo[]
     */
    protected function performQuery(DataQueryInterface $query)
    {
        if (! ($query instanceof FileContentsDataQuery))
            throw new DataConnectionQueryTypeError($this, 'DataConnector "' . $this->getAliasWithNamespace() . '" expects an instance of FileContentsDataQuery as query, "' . get_class($query) . '" given instead!', '6T5W75J');
        
        // Check if the file exists and add the splFileInfo and contents resolver to the query
        $fileInfo = new DataSourceFileInfo($query->getPathAbsolute(), $this->getWorkbench());
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
     * @return FileContentsConnector
     */
    public function setErrorIfFileNotFound(bool $value) : DataSourceFileContentsConnector
    {
        $this->error_if_file_not_found = $value;
        return $this;
    }
}