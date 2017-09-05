<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\DataSheets\DataSheetMapperError;
use exface\Core\Exceptions\DataSheets\DataSheetMapperInvalidInputError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataMappingInterface;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\DataSheets\DataColumnMappingInterface;

/**
 * Maps data from one data sheet to another using mappers for columns, filters, sorters, etc.
 * 
 * @see DataSheetMapperInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSheetMapper implements DataSheetMapperInterface {
    
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $fromMetaObject = null;
    
    private $toMetaObject = null;
    
    private $columnMappings = [];
    
    public function __construct(Workbench $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::map()
     */
    public function map(DataSheetInterface $fromSheet)
    {
        if (! $this->getFromMetaObject()->is($fromSheet->getMetaObject())){
            throw new DataSheetMapperInvalidInputError($fromSheet, $this, 'Input data sheet based on "' . $fromSheet->getMetaObject()->getAliasWithNamespace() . '" does not match the input object of the mapper "' . $this->getFromMetaObject()->getAliasWithNamespace() . '"!');
        }
        
        // Make sure, the from-sheet has everything needed
        $fromSheet = $this->prepareFromSheet($fromSheet);
        
        // Create an empty to-sheet
        $toSheet = DataSheetFactory::createFromObject($this->getToMetaObject());
        
        // Fill the to-sheet with the mappings
        foreach ($this->getColumnMappings() as $map){
            $toSheet = $map->map($fromSheet, $toSheet);
        }
        
        return $toSheet;
    }
    
    /**
     * Checks if all required columns are in the from-sheet and tries to add missing ones and reload the data.
     * 
     * @param DataSheetInterface $data_sheet
     * 
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    protected function prepareFromSheet(DataSheetInterface $data_sheet)
    {
        // Only try to add new columns if the sheet has a UID column and is fresh (no values changed)
        if ($data_sheet->hasUidColumn() && $data_sheet->isFresh()){
            foreach ($this->getColumnMappings() as $map){
                $from_expression = $map->getFromExpression();
                if (! $data_sheet->getColumns()->getByExpression($from_expression)){
                    $data_sheet->getColumns()->addFromExpression($from_expression);
                }
            }
            if (! $data_sheet->isFresh()){
                $data_sheet->addFilterFromColumnValues($data_sheet->getUidColumn());
                $data_sheet->dataRead();
            }
        }
        return $data_sheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        // TODO
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getFromMetaObject()
     */
    public function getFromMetaObject()
    {
        if (is_null($this->fromMetaObject)){
            // TODO add error code
            throw new DataSheetMapperError($this, 'No from-object defined in data sheet mapper!');
        }
        
        return $this->fromMetaObject;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setFromMetaObject()
     */
    public function setFromMetaObject(MetaObjectInterface $object)
    {
        $this->fromMetaObject = $object;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setFromObjectAlias()
     */
    public function setFromObjectAlias($alias_with_namespace)
    {
        return $this->setFromMetaObject($this->getWorkbench()->model()->getObject($alias_with_namespace));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getToMetaObject()
     */
    public function getToMetaObject()
    {
        if (is_null($this->toMetaObject)){
            // TODO add error code
            throw new DataSheetMapperError($this, 'No to-object defined in data sheet mapper!');
        }
        return $this->toMetaObject;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setToMetaObject()
     */
    public function setToMetaObject(MetaObjectInterface $toMetaObject)
    {
        $this->toMetaObject = $toMetaObject;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getColumnMappings()
     */
    public function getColumnMappings()
    {
        return $this->columnMappings;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setColumnMappings()
     */
    public function setColumnMappings(array $ColumnMappingsOrUxonObjects)
    {
        foreach ($ColumnMappingsOrUxonObjects as $instance){
            if ($instance instanceof DataColumnMappingInterface){
                $map = $instance;
            } elseif ($instance instanceof UxonObject){
                $map = $this->createDataColumnMapping($instance);            
            } else {
                throw new DataSheetMapperError($this, 'Invalid format "' . gettype($instance) . '" of column mapping given: expecting instantiated DataColumnMapping or its UXON description!');
            }
            
            $this->addColumnMapping($map);
        }
        return $this;
    }
    
    /**
     * @return DataColumnMappingInterface
     */
    protected function createDataColumnMapping(UxonObject $uxon = null)
    {
        $mapping = new DataColumnMapping($this);
        if (!is_null($uxon)){
            $mapping->importUxonObject($uxon);
        }
        return $mapping;
    }
    
   /**
    * 
    * {@inheritDoc}
    * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::addColumnMapping()
    */
    public function addColumnMapping(DataColumnMappingInterface $map)
    {
        $this->columnMappings[] = $map;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setExpressionMappings()
     */
    public function setExpressionMappings(array $uxonObjects)
    {
        foreach ($uxonObjects as $instance){
            if ($instance instanceof UxonObject){
                $column = $this->createDataColumnMapping($instance);
                $this->addColumnMapping($column);
                
                // TODO map filters, sorters and aggregators
            } else {
                throw new DataSheetMapperError($this, 'Invalid format "' . gettype($instance) . '" of expression mapping given: expecting UXON mapping description!');
            }
        }
    }
    
}