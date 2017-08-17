<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use exface\Core\DataConnectors\TransparentConnector;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;

class FileContentsConnector extends TransparentConnector
{

    private $base_path = null;

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
        
        if (Filemanager::pathIsAbsolute($this->getBasePath())) {
            $base_path = Filemanager::pathJoin($this->getWorkbench()->filemanager()->getPathToBaseFolder(), $this->getBasePath());
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
        
        if (! file_exists($query->getPathAbsolute())) {
            throw new DataQueryFailedError($query, 'File "' . $query->getPathAbsolute() . '" not found!');
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
     * Sets the base path for the connection.
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
}
?>