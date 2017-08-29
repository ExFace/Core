<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\DataSheets\DataSheetExpressionMap;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Exceptions\DataSheets\DataSheetMapperError;

interface DataSheetMapperInterface extends iCanBeConvertedToUxon, ExfaceClassInterface
{

    /**
     * 
     * @param DataSheetInterface $fromSheet
     * @return DataSheetInterface
     */
    public function map(DataSheetInterface $fromSheet);
    
    /**
     *
     * @throws DataSheetMapperError if no from-object set
     * 
     * @return Object
     */
    public function getFromMetaObject();
    
    /**
     * @param Object $object
     * @return DataSheetMapperInterface
     */
    public function setFromMetaObject(Object $object);
    
    /**
     *
     * @param string $alias_with_namespace
     * @return DataSheetMapperInterface
     */
    public function setFromObjectAlias($alias_with_namespace);
    
    /**
     * @return Object
     */
    public function getToMetaObject();
    
    /**
     * @param Object $toMetaObject
     */
    public function setToMetaObject(Object $toMetaObject);
    
    /**
     * @return DataSheetExpressionMap[]
     */
    public function getExpressionMaps();
    
    /**
     *
     * @param DataSheetExpressionMap[]|UxonObject[]
     * @return DataSheetMapperInterface
     */
    public function setExpressionMaps(array $expressionMapsOrUxonObjects);
    
    /**
     *
     * @param DataSheetExpressionMap $map
     * @return DataSheetMapperInterface
     */
    public function addExpressionMap(DataSheetExpressionMap $map);
   
}