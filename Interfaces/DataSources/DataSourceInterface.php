<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\CommonLogic\Model\Model;
use exface\Core\Interfaces\WorkbenchDependantInterface;

interface DataSourceInterface extends WorkbenchDependantInterface
{

    /**
     *
     * @return DataConnectionInterface
     */
    public function getConnection() : DataConnectionInterface;
    
    /**
     * 
     * @param DataConnectionInterface $connection
     * @return DataSourceInterface
     */
    public function setConnection(DataConnectionInterface $connection) : DataSourceInterface;

    /**
     *
     * @return string
     */
    public function getId();

    /**
     *
     * @param string $value            
     */
    public function setId($value);

    /**
     *
     * @return string
     */
    public function getQueryBuilderAlias();

    /**
     *
     * @param string $value            
     */
    public function setQueryBuilderAlias($value);

    /**
     * Returns TRUE if write-opertaions are allowed on the data source with it's 
     * current connection and FALSE otherwise.
     *
     * @return boolean
     */
    public function isWritable();

    /**
     * Set to FALSE to mark this data source as read only.
     *
     * @param boolean $value            
     * @return DataSourceInterface
     */
    public function setWritable($value);
    
    /**
     * Returns TRUE if read-operations are allowed on the data source and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isReadable();
    
    /**
     * Set to TRUE to prevent read-operations on this data source.
     * 
     * @param boolean $true_or_false
     * @return DataSourceInterface
     */
    public function setReadable($true_or_false);

    /**
     *
     * @return Model
     */
    public function getModel();
    
    /**
     * 
     * @param string $readableName
     * @return DataSourceInterface
     */
    public function setName(string $readableName) : DataSourceInterface;
    
    /**
     * 
     * @return string
     */
    public function getName() : string;
}
?>