<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;

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
     * Returns an array of expression instances required in the from-sheet in order for the mapper to work.
     * 
     * @param DataSheetInterface $dataSheet
     * @return ExpressionInterface[]
     */
    public function getRequiredExpressions(DataSheetInterface $fromSheet) : array;
}