<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\AppInterface;

interface ModelBuilderInterface
{

    public function __construct(SqlDataConnectorInterface $data_connector);

    /**
     *
     * @param MetaObjectInterface $meta_object    
     *
     */
    public function generateModelForObject(MetaObjectInterface $meta_object);
    
    /**
     *
     * @param AppInterface $app
     *
     */
    public function generateModelForApp(AppInterface $app);
    

    /**
     *
     * @return SqlDataConnectorInterface
     */
    public function getDataConnection();
    
    /**
     * Returns the number of processed model entities, that were already present in the model.
     * 
     * @return integer
     */
    public function countSkippedEntities();
    
    /**
     * Returns the number of successfully created model entities
     * 
     * @return integer
     */
    public function countCreatedEntities();
}

?>