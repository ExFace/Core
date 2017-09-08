<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\Interfaces\Model\MetaObjectInterface;

interface ModelizerInterface
{

    public function __construct(SqlDataConnectorInterface $data_connector);

    /**
     *
     * @param MetaObjectInterface $meta_object            
     * @param string $table_name            
     *
     */
    public function getAttributePropertiesFromTable(MetaObjectInterface $meta_object, $table_name);

    /**
     *
     * @return SqlDataConnectorInterface
     */
    public function getDataConnection();
}

?>