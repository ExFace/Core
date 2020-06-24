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
use exface\Core\Factories\DataColumnFactory;
use exface\Core\Interfaces\DataSheets\DataColumnToFilterMappingInterface;
use exface\Core\Interfaces\DataSheets\DataFilterToColumnMappingInterface;

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
    
    private $filterColumnMappings = [];
    
    private $inheritColumns = null;
    
    private $inheritFilters = null;
    
    private $inheritSorters = null;
    
    private $refreshDataAfterMapping = false;
    
    public function __construct(Workbench $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::map()
     */
    public function map(DataSheetInterface $fromSheet) : DataSheetInterface
    {
        if (! $this->getFromMetaObject()->is($fromSheet->getMetaObject())){
            throw new DataSheetMapperInvalidInputError($fromSheet, $this, 'Input data sheet based on "' . $fromSheet->getMetaObject()->getAliasWithNamespace() . '" does not match the input object of the mapper "' . $this->getFromMetaObject()->getAliasWithNamespace() . '"!');
        }
        
        // Make sure, the from-sheet has everything needed
        $fromSheet = $this->prepareFromSheet($fromSheet);
        
        // Create an empty to-sheet
        $toSheet = DataSheetFactory::createFromObject($this->getToMetaObject());
        
        // Inherit columns if neccessary
        if ($this->getInheritColumns()){
            foreach ($fromSheet->getColumns() as $fromCol){
                $toSheet->getColumns()->add(DataColumnFactory::createFromUxon($toSheet, $fromCol->exportUxonObject()));
            }
            $toSheet->importRows($fromSheet);
        }
        
        // Inherit filters if neccessary
        if ($this->getInheritFilters()){
            $toSheet->setFilters($fromSheet->getFilters());
        }
        
        // Inherit sorters if neccessary
        if ($this->getInheritSorters()){
            foreach ($fromSheet->getSorters()->getAll() as $sorter) {
                $toSheet->getSorters()->add($sorter);
            }
        }
        
        // Map columns to columns
        foreach ($this->getMappings() as $map){
            $toSheet = $map->map($fromSheet, $toSheet);
        }
        
        // Refresh data if needed
        if ($this->getRefreshDataAfterMapping()) {
            $toSheet->dataRead();
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
    protected function prepareFromSheet(DataSheetInterface $data_sheet) : DataSheetInterface
    {
        // Only try to add new columns if the sheet has a UID column and is fresh (no values changed)
        if ($data_sheet->hasUidColumn(true) && $data_sheet->isFresh()){
            $additionSheet = null;
            // See if any mapped columns are missing in the original data sheet. If so, add empty
            // columns and also create a separate sheet for reading missing data.
            foreach ($this->getColumnToColumnMappings() as $map){
                $from_expression = $map->getFromExpression();
                if (! $data_sheet->getColumns()->getByExpression($from_expression)){
                    if ($additionSheet === null) {
                        $additionSheet = $data_sheet->copy();
                        foreach ($additionSheet->getColumns() as $col) {
                            if ($col !== $additionSheet->getUidColumn()) {
                                $additionSheet->getColumns()->remove($col);
                            }
                        }
                    }
                    $data_sheet->getColumns()->addFromExpression($from_expression);
                    $additionSheet->getColumns()->addFromExpression($from_expression);
                }
            }
            // If columns were added to the original sheet, that need data to be loaded,
            // use the additional data sheet to load the data. This makes sure, the values
            // in the original sheet (= the input values) are not overwrittten by the read
            // operation.
            if (! $data_sheet->isFresh()){
                $additionSheet->getFilters()->addConditionFromColumnValues($data_sheet->getUidColumn());
                $additionSheet->dataRead();
                $uidCol = $data_sheet->getUidColumn();
                foreach ($additionSheet->getColumns() as $addedCol) {
                    foreach ($additionSheet->getRows() as $row) {
                        $uid = $row[$uidCol->getName()];
                        $rowNo = $uidCol->findRowByValue($uid);
                        if ($uid === null || $rowNo === false) {
                            throw new DataSheetMapperError($this, 'Cannot load additional data in preparation for mapping!');
                        }
                        $data_sheet->setCellValue($addedCol->getName(), $rowNo, $row[$addedCol->getName()]);
                    }
                }
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
    public function getFromMetaObject() : MetaObjectInterface
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
    public function setFromMetaObject(MetaObjectInterface $object) : DataSheetMapperInterface
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
    public function setFromObjectAlias(string $alias_with_namespace) : DataSheetMapperInterface
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
    public function getToMetaObject() : MetaObjectInterface
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
    public function setToMetaObject(MetaObjectInterface $toMetaObject) : DataSheetMapperInterface
    {
        $this->toMetaObject = $toMetaObject;
        return $this;
    }
    
    /**
     * The object of the resulting data sheet (after the mapping).
     *
     * Only set to `to_object_alias` explicitly if really neccessary. Leave empty for the
     * mapper owner (e.g. action) to set the target object automatically.
     *
     * @uxon-property to_object_alias
     * @uxon-type metamodel:object
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setFromObjectAlias()
     */
    public function setToObjectAlias(string $alias_with_namespace) : DataSheetMapperInterface
    {
        return $this->setToMetaObject($this->getWorkbench()->model()->getObject($alias_with_namespace));
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getColumnToColumnMappings()
     */
    protected function getColumnToColumnMappings() : array
    {
        return $this->columnMappings;
    }

    /**
     * @deprecated Obsolet! Use setColumnToColumnMappings()
     */
    public function setColumnMappings(UxonObject $uxon) : DataSheetMapperInterface
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
    public function setColumnToColumnMappings(UxonObject $uxon) : DataSheetMapperInterface
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
     * @uxon-template [{"from": "", "to": "", "comparator": "="}]
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setColumnToFilterMappings()
     */
    public function setColumnToFilterMappings(UxonObject $uxon) : DataSheetMapperInterface
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
    public function addColumnToFilterMapping(DataColumnToFilterMappingInterface $map) : DataSheetMapperInterface
    {
        $this->columnFilterMappings[] = $map;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getColumnToFilterMappings()
     */
    protected function getColumnToFilterMappings() : array
    {
        return $this->columnFilterMappings;
    }
    
    /**
     * Creates columns from the values of filters
     *
     * @uxon-property filter_to_column_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataFilterToColumnMapping[]
     * @uxon-template [{"from": "", "from_comparator": "", "to": "", "to_single_row": false}]
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setFilterToColumnMappings()
     */
    public function setFilterToColumnMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $instance){
            $map = $this->createFilterToColumnMapping($instance);
            $this->addFilterToColumnMapping($map);
        }
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::addColumnToFilterMapping()
     */
    public function addFilterToColumnMapping(DataFilterToColumnMappingInterface $map) : DataSheetMapperInterface
    {
        $this->filterColumnMappings[] = $map;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getColumnToFilterMappings()
     */
    protected function getFilterToColumnMappings() : array
    {
        return $this->filterColumnMappings;
    }
    
    /**
     * @return DataFilterToColumnMappingInterface
     */
    protected function createFilterToColumnMapping(UxonObject $uxon = null) : DataFilterToColumnMappingInterface
    {
        $mapping = new DataFilterToColumnMapping($this);
        if (!is_null($uxon)){
            $mapping->importUxonObject($uxon);
        }
        return $mapping;
    }
    
   /**
    * 
    * {@inheritDoc}
    * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getMappings()
    */
    public function getMappings() : array
    {
        return array_merge(
            $this->getColumnToColumnMappings(),
            $this->getColumnToFilterMappings(),
            $this->getFilterToColumnMappings()
        );
    }
    
    /**
     * @return DataColumnMappingInterface
     */
    protected function createColumnToColumnMapping(UxonObject $uxon = null) : DataColumnMappingInterface
    {
        $mapping = new DataColumnMapping($this);
        if (!is_null($uxon)){
            $mapping->importUxonObject($uxon);
        }
        return $mapping;
    }
    
    /**
     * @return DataColumnToFilterMappingInterface
     */
    protected function createColumnToFilterMapping(UxonObject $uxon = null) : DataColumnToFilterMappingInterface
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
    public function addColumnToColumnMapping(DataColumnMappingInterface $map) : DataSheetMapperInterface
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
    public function setExpressionMappings(UxonObject $uxonObjects) : DataSheetMapperInterface
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
    protected function getInheritColumns() : bool
    {
        return $this->inheritColumns ?? $this->canInheritColumns();
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
    public function setInheritColumns(bool $value) : DataSheetMapperInterface
    {
        if ($value){
            if (! $this->canInheritColumns()) {
                throw new DataSheetMapperError($this, 'Data sheets of object "' . $this->getToMetaObject()->getAliasWithNamespace() . '" cannot inherit columns from sheets of "' . $this->getFromMetaObject() . '"!');
            }
        }
        
        $this->inheritColumns = $value;
        return $this;
    }
    
    /**
     * Returns TRUE if columns of the from-sheet should be inherited by the to-sheet.
     *
     * By default, this will be TRUE if the to-sheet is based on the same object as the
     * from-sheet or a derivative and FALSE otherwise.
     *
     * @return boolean
     */
    protected function getInheritFilters() : bool
    {
        return $this->inheritFilters ?? $this->canInheritFilters();
    }
    
    /**
     * Set to FALSE to prevent the to-sheet from inheriting compatible filters from the from-sheet.
     *
     * If the to-sheet is based on the same object as the from-sheet or a derivative,
     * the mapper will copy all filters by default and apply the mapping afterwards.
     * This option can prevent this behavior.
     *
     * @uxon-property inherit_filters
     * @uxon-type boolean
     *
     * @param boolean $true_or_false
     * @throws DataSheetMapperError
     * @return \exface\Core\CommonLogic\DataSheets\DataSheetMapper
     */
    public function setInheritFilters(bool $value) : DataSheetMapperInterface
    {
        if ($value){
            if (! $this->canInheritFilters()) {
                throw new DataSheetMapperError($this, 'Data sheets of object "' . $this->getToMetaObject()->getAliasWithNamespace() . '" cannot inherit filters from sheets of "' . $this->getFromMetaObject() . '"!');
            }
        }
        
        $this->inheritFilters = $value;
        return $this;
    }
    
    /**
     * Returns TRUE if columns of the from-sheet should be inherited by the to-sheet.
     *
     * By default, this will be TRUE if the to-sheet is based on the same object as the
     * from-sheet or a derivative and FALSE otherwise.
     *
     * @return boolean
     */
    protected function getInheritSorters() : bool
    {
        return $this->inheritSorters ?? $this->canInheritSorters();
    }
    
    /**
     * Set to FALSE to prevent the to-sheet from inheriting compatible sorters from the from-sheet.
     *
     * If the to-sheet is based on the same object as the from-sheet or a derivative,
     * the mapper will copy all sorters by default and apply the mapping afterwards.
     * This option can prevent this behavior.
     *
     * @uxon-property inherit_sorters
     * @uxon-type boolean
     *
     * @param boolean $true_or_false
     * @throws DataSheetMapperError
     * @return \exface\Core\CommonLogic\DataSheets\DataSheetMapper
     */
    public function setInheritSorters(bool $value) : DataSheetMapperInterface
    {
        if ($value){
            if (! $this->canInheritSorters()) {
                throw new DataSheetMapperError($this, 'Data sheets of object "' . $this->getToMetaObject()->getAliasWithNamespace() . '" cannot inherit sorters from sheets of "' . $this->getFromMetaObject() . '"!');
            }
        }
        
        $this->inheritSorters = $value;
        return $this;
    }
    
    /**
     * Returns TRUE if columns of the from-sheet sheet can be inherited by the to-sheet.
     * 
     * @return boolean
     */
    protected function canInheritColumns() : bool
    {
        return $this->getToMetaObject()->is($this->getFromMetaObject());
    }
    
    /**
     * 
     * @return bool
     */
    protected function canInheritFilters() : bool
    {
        return $this->canInheritColumns();
    }
    
    /**
     * 
     * @return bool
     */
    protected function canInheritSorters() : bool
    {
        return $this->canInheritColumns();
    }
    
    /**
     *
     * @return bool
     */
    protected function getRefreshDataAfterMapping() : bool
    {
        return $this->refreshDataAfterMapping;
    }
    
    /**
     * Set to TRUE to read data after all mappings were performed.
     * 
     * @uxon-property refresh_data_after_mapping
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setRefreshDataAfterMapping()
     */
    public function setRefreshDataAfterMapping(bool $trueOrFalse) : DataSheetMapperInterface
    {
        $this->refreshDataAfterMapping = $trueOrFalse;
        return $this;
    }
}