<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\UxonObject;

interface DataSourceInterface extends ExfaceClassInterface
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
     * Returns TRUE, if the data source or it's connection is marked as read only or FALSE otherwise.
     *
     * @return boolean
     */
    public function isReadOnly();

    /**
     * Set to TRUE to mark this data source as read only.
     *
     * @param boolean $value            
     * @return DataSourceInterface
     */
    public function setReadOnly($value);

    /**
     *
     * @return Model
     */
    public function getModel();
}
?>