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
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\DataSheets\DataColumnMappingInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\DataColumnFactory;
use exface\Core\Interfaces\DataSheets\DataColumnToFilterMappingInterface;

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
    
    private $columnFilterMappings = [];
    
    private $inheritColumns = null;
    
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
        
        if ($this->getInheritColumns()){
            foreach ($fromSheet->getColumns() as $fromCol){
                $toSheet->getColumns()->add(DataColumnFactory::createFromUxon($toSheet, $fromCol->exportUxonObject()));
            }
            $toSheet->importRows($fromSheet);
        }
        
        // Map columns to columns
        foreach ($this->getMappings() as $map){
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
        if ($data_sheet->hasUidColumn(true) && $data_sheet->isFresh()){
            foreach ($this->getColumnToColumnMappings() as $map){
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
     * The object to apply the mapping to (= the input of the mapping).
     * 
     * The mapping will only be applied to input data of this object or it's
     * derivatives!
     * 
     * @uxon-property from_object_alias
     * @uxon-type metamodel:object
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setFromObjectAlias()
     */
    public function setFromObjectAlias($alias_with_namespace)
    {
        return $this->setFromMetaObject($this->getWorkbench()->model()->getObject($alias_with_namespace));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
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
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getColumnToColumnMappings()
     */
    public function getColumnToColumnMappings()
    {
        return $this->columnMappings;
    }

    /**
     * @deprecated Obsolet! Use setColumnToColumnMappings()
     */
    public function setColumnMappings(UxonObject $uxon)
    {
        return $this->setColumnToColumnMappings($uxon);
    }
    
    /**
     * Maps column expressions of the from-sheet to new columns of the to-sheet.
     * 
     * @uxon-property column_to_column_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataColumnMapping[]
     * @uxon-template [{"from": "", "to": ""}]
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setColumnToColumnMappings()
     */
    public function setColumnToColumnMappings(UxonObject $uxon)
    {
        foreach ($uxon as $instance){
            $map = $this->createColumnToColumnMapping($instance);
            $this->addColumnToColumnMapping($map);
        }
        return $this;
    }
    
    /**
     * Creates filters from the values of a column
     * 
     * @uxon-property column_to_filter_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataColumnToFilterMapping[]
     * @uxon-template [{"from": "", "to": "", "comparator": "["}]
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setColumnToFilterMappings()
     */
    public function setColumnToFilterMappings(UxonObject $uxon)
    {
        foreach ($uxon as $instance){
            $map = $this->createColumnToFilterMapping($instance);
            $this->addColumnToColumnMapping($map);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::addColumnToFilterMapping()
     */
    public function addColumnToFilterMapping(DataColumnToFilterMappingInterface $map) {
        $this->columnFilterMappings[] = $map;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getColumnToFilterMappings()
     */
    public function getColumnToFilterMappings()
    {
        return $this->columnFilterMappings;
    }
    
   /**
    * 
    * {@inheritDoc}
    * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getMappings()
    */
    public function getMappings()
    {
        return array_merge(
                $this->getColumnToColumnMappings(),
                $this->getColumnToFilterMappings()
            );
    }
    
    /**
     * @return DataColumnMappingInterface
     */
    protected function createColumnToColumnMapping(UxonObject $uxon = null)
    {
        $mapping = new DataColumnMapping($this);
        if (!is_null($uxon)){
            $mapping->importUxonObject($uxon);
        }
        return $mapping;
    }
    
    /**
     * @return DataColumnToFilterMapping
     */
    protected function createColumnToFilterMapping(UxonObject $uxon = null)
    {
        $mapping = new DataColumnToFilterMapping($this);
        if (!is_null($uxon)){
            $mapping->importUxonObject($uxon);
        }
        return $mapping;
    }
    
   /**
    * 
    * {@inheritDoc}
    * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::addColumnToColumnMapping()
    */
    public function addColumnToColumnMapping(DataColumnMappingInterface $map)
    {
        $this->columnMappings[] = $map;
        return $this;
    }

    /**
     * Map anything using provided expressions: columns, filters, sorters, aggregators, etc.
     * 
     * @uxon-property expression_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataColumnMapping[]
     * @uxon-template [{"from": "", "to": ""}]
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setExpressionMappings()
     */
    public function setExpressionMappings(UxonObject $uxonObjects)
    {
        return $this->setColumnMappings($uxonObjects);
    }
    
    /**
     * Returns TRUE if columns of the from-sheet should be inherited by the to-sheet.
     * 
     * By default, this will be TRUE if the to-sheet is based on the same object as the 
     * from-sheet or a derivative and FALSE otherwise.
     * 
     * @return boolean
     */
    public function getInheritColumns()
    {
        if (is_null($this->inheritColumns)){
            if ($this->canInheritColumns()){
                return true;
            } else {
                return false;
            }
        }
        return $this->inheritColumns;
    }
    
    /**
     * Set to FALSE to prevent the to-sheet from inheriting compatible columns from the from-sheet.
     * 
     * If the to-sheet is based on the same object as the from-sheet or a derivative,
     * the mapper will copy all columns by default and apply the mapping afterwards.
     * This option can prevent this behavior.
     * 
     * @uxon-property inherit_columns
     * @uxon-type boolean
     * 
     * @param boolean $true_or_false
     * @throws DataSheetMapperError
     * @return \exface\Core\CommonLogic\DataSheets\DataSheetMapper
     */
    public function setInheritColumns($true_or_false)
    {
        $value = BooleanDataType::cast($true_or_false);
        
        if ($value){
            if (! $this->canInheritColumns()) {
                throw new DataSheetMapperError($this, 'Data sheets of object "' . $this->getToMetaObject()->getAliasWithNamespace() . '" cannot inherit columns from sheets of "' . $this->getFromMetaObject() . '"!');
            }
        }
        
        $this->inheritColumns = $value;
        return $this;
    }
    
    /**
     * Returns TRUE if columns of the from-sheet sheet can be inherited by the to-sheet.
     * 
     * @return boolean
     */
    protected function canInheritColumns()
    {
        return $this->getToMetaObject()->is($this->getFromMetaObject());
    }
    
}