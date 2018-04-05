<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * Base interface for DataColumnMappers, DataSorterMappers, etc. to ensure they all work alike.
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataMappingInterface extends iCanBeConvertedToUxon, WorkbenchDependantInterface
{
    
    /**
     *
     * @return DataSheetMapperInterface
     */
    public function getMapper();

    /**
     * 
     * @param DataSheetInterface $fromSheet
     * @param DataSheetInterface $toSheet
     * @return DataMappingInterface
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet);
    
    /**
     * 
     * @param string $string
     * @return DataMappingInterface
     */
    public function setFrom($string);
    
    /**
     *
     * @param string|UxonObject $stringOrUxonObject
     * @return DataMappingInterface
     */
    public function setTo($stringOrUxonObject);
   
}