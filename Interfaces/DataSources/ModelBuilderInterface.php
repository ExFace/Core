<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

interface ModelBuilderInterface
{

    /**
     * Generates attributes for the given meta object based on its data source.
     * 
     * The returned data sheet contains the new attributes as rows and the number of attributes
     * read from the data source as the total row counter (that is those created and
     * those skipped as duplicates in total). 
     * 
     * @param MetaObjectInterface $meta_object  
     * @param string $addressPattern  
     * @return DataSheetInterface
     *
     */
    public function generateAttributesForObject(MetaObjectInterface $meta_object, string $addressPattern = '') : DataSheetInterface;
    
    /**
     * Generates meta objects for the specified app from all data addresses existing 
     * in the given data source optionally filtered by the data address mask.
     * 
     * The returned data sheet contains the new objects as rows and the number of objects
     * read from the data source as the total row counter (that is those created and
     * those skipped as duplicates in total). 
     * 
     * The syntax of the mask depends on the implementation of the model builder. Thus,
     * the default SQL model builders will support regular LIKE syntax with %-characters
     * as wildcards, while model builders parsing XML descriptions will use XPath expressions
     * as address masks.
     * 
     * @param AppInterface $app
     * @param DataSourceInterface $source
     * @param string $data_address_mask
     * 
     * @return DataSheetInterface
     *
     */
    public function generateObjectsForDataSource(AppInterface $app, DataSourceInterface $source, string $data_address_mask = null) : DataSheetInterface;
    

    /**
     *
     * @return DataConnectionInterface
     */
    public function getDataConnection() : DataConnectionInterface;
}

?>