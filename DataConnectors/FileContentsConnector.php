<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;

class FileContentsConnector extends TransparentConnector
{

    private $base_path = null;
    
    private $error_if_file_not_found = false;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        $base_path = $this->getBasePath();
        
        if (is_null($base_path)) {
            $base_path = $this->getWorkbench()->filemanager()->getPathToBaseFolder();
        }
        
        if ($this->getBasePath() !== null && false === Filemanager::pathIsAbsolute($this->getBasePath())) {
            $base_path = Filemanager::pathJoin([
                $this->getWorkbench()->filemanager()->getPathToBaseFolder(), 
                $this->getBasePath()
            ]);
        }
        
        $this->setBasePath($base_path);
        return;
    }

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
        
        // If the query does not have a base path, use the base path of the connection
        if (! $query->getBasePath()) {
            $query->setBasePath($this->getBasePath());
        }
        
        // Check if the file exists and add the splFileInfo and contents resolver to the query
        $path = $query->getPathAbsolute();
        if (file_exists($path)) {
            $query->setFileInfo(new \SplFileInfo($path));
            $query->setFileContents(function(FileContentsDataQuery $query) {
                return file_get_contents($query->getPathAbsolute()); 
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
     * Returns the current base path
     *
     * @return string
     */
    public function getBasePath()
    {
        return $this->base_path;
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
     * @return \exface\Core\DataConnectors\FileContentsConnector
     */
    public function setBasePath($value)
    {
        if (! is_null($value)) {
            $this->base_path = Filemanager::pathNormalize($value, '/');
        }
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
     * @return FileContentsConnector
     */
    public function setErrorIfFileNotFound(bool $value) : FileContentsConnector
    {
        $this->error_if_file_not_found = $value;
        return $this;
    }
}