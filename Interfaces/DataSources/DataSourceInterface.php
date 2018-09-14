<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\CommonLogic\UxonObject;

interface DataSourceInterface extends WorkbenchDependantInterface
{

    /**
     *
     * @return DataConnectionInterface
     */
    public function getConnection();

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
    public function getDataConnectorAlias();

    /**
     *
     * @param string $value            
     */
    public function setDataConnectorAlias($value);

    /**
     *
     * @return string
     */
    public function getConnectionId();

    /**
     *
     * @param string $value            
     */
    public function setConnectionId($value);

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
     * Returns a UXON object with configuration options for this connections (e.g.
     * [user => user_value, password => password_value, ...]
     *
     * @return UxonObject
     */
    public function getConnectionConfig();

    /**
     *
     * @param UxonObject $value            
     */
    public function setConnectionConfig(UxonObject $value);

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