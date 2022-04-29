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
use exface\Core\Factories\DataColumnFactory;
use exface\Core\Interfaces\DataSheets\DataFilterToColumnMappingInterface;
use exface\Core\Uxon\DataSheetMapperSchema;
use exface\Core\Interfaces\DataSheets\DataMappingInterface;

/**
 * Maps data from one data sheet to another using different types of mappings for columns, filters, etc.
 * 
 * The mapper performs multiple mapping operations consequently transfering (= mapping) 
 * data from the from-data-sheet to the to-data-sheet. 
 * 
 * ## Mappings types 
 * 
 * How exactly the data is mapped depends on the type of mapping being used: 
 * 
 * - `column_to_column_mappings` transfer values from columns of the from-sheet to columns
 * in the to-sheet. Their `from` expression can also be a calculation allowing to change
 * values within the mapping (e.g. `=(version + 1)` or even use static calculation like `=Now()`.
 * - `column_to_filter_mappings` create filters in the to-sheet from values of from-heet columns.
 * - `filter_to_column_mappings` fill to-sheet columns with values of from-sheet filters.
 * - `joins` can join arbitrary data in a way similar to SQL JOINs
 * 
 * ## Order of execution
 * 
 * Mappings are applied in the order of definition: e.g. if you place `joins` first in the mappers 
 * UXON followed by `column_to_column_mappings`, the column mappings will be applied after the
 * data was joined, so you will be able to map newly joined columns.
 * 
 * If you need full control over the order of the mappings, use the generic `mappings` array
 * where you must define the class of each mapping however. All the specific arrays like
 * `column_to_column_mappings` are just there for convenience - technically they all just fill 
 * the `mappings`.
 * 
 * ## Inheriting properties of the from-sheet
 * 
 * To decrease the number of explicit mappings, the mapper can make the to-sheet inherit columns, 
 * filter and other things from the to-sheet:
 * 
 * - `inherit_columns`
 * - `inherit_column_only_for_system_attributes`
 * - `inherit_filters`
 * - `inherit_sorters`
 * 
 * @see DataSheetMapperInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSheetMapper implements DataSheetMapperInterface 
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $fromMetaObject = null;
    
    private $toMetaObject = null;
    
    private $mappings = [];
    
    private $inheritColumns = null;
    
    private $inheritColumnsOnlySystem = false;
    
    private $inheritFilters = null;
    
    private $inheritSorters = null;
    
    private $refreshDataAfterMapping = false;
    
    private $readMissingData = true;
    
    public function __construct(Workbench $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, bool $readMissingColumns = null) : DataSheetInterface
    {
        if (! $this->getFromMetaObject()->is($fromSheet->getMetaObject())){
            throw new DataSheetMapperInvalidInputError($fromSheet, $this, 'Input data sheet based on "' . $fromSheet->getMetaObject()->getAliasWithNamespace() . '" does not match the input object of the mapper "' . $this->getFromMetaObject()->getAliasWithNamespace() . '"!');
        }
        
        // Make sure, the from-sheet has everything needed
        $fromSheet = $this->prepareFromSheet($fromSheet, $readMissingColumns);
        
        // Create an empty to-sheet
        $toSheet = DataSheetFactory::createFromObject($this->getToMetaObject());
        
        // Inherit columns if neccessary
        if ($this->getInheritColumns()){
            foreach ($fromSheet->getColumns() as $fromCol){
                if ($this->getInheritColumnsOnlyForSystemAttributes() && (! $fromCol->isAttribute() || ! $fromCol->getAttribute()->isSystem())) {
                    continue;
                }
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
    protected function prepareFromSheet(DataSheetInterface $data_sheet, bool $readMissingColumns = null) : DataSheetInterface
    {
        // If reading missing column not requested explicitly, add them automatically 
        // if the sheet has a UID column and is fresh (no values changed)
        $readMissingColumns = $readMissingColumns ?? ($data_sheet->hasUidColumn(true) && $data_sheet->isFresh());
        
        if ($readMissingColumns !== true) {
            return $data_sheet;
        }
        
        if ($data_sheet->isEmpty()) {
            // Only use mappings that use a from-column
            // TODO give mappings a prepare() method and put the logic in there. But how to make sure the
            // passed data sheet is not altered? Pass a copy?
            foreach ($this->getMappings() as $map){
                if ($map instanceof DataFilterToColumnMappingInterface || $map instanceof DataJoinMapping) {
                    continue;
                }
                $data_sheet->getColumns()->addFromExpression($map->getFromExpression());
            }
            $data_sheet->dataRead();
            return $data_sheet;
        }
        
        $additionSheet = null;
        // See if any mapped columns are missing in the original data sheet. If so, add empty
        // columns and also create a separate sheet for reading missing data.
        foreach ($this->getMappings() as $map){
            // Only use mappings that use a from-column
            // TODO give mappings a prepare() method and put the logic in there - see other comment few lines above.
            if ($map instanceof DataFilterToColumnMappingInterface || $map instanceof DataJoinMapping) {
                continue;
            }
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
        if (! $data_sheet->isFresh() && $this->getReadMissingFromData() === true){
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
     * Map column expressions of the from-sheet to new columns of the to-sheet.
     * 
     * @uxon-property mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\AbstractDataSheetMapping[]
     * @uxon-template [{"class": "", "": ""}]
     * 
     * @param UxonObject $uxonArray
     * @throws DataSheetMapperError
     * @return DataSheetMapperInterface
     */
    protected function setMappings(UxonObject $uxonArray) : DataSheetMapperInterface
    {
        foreach ($uxonArray as $uxon) {
            $class = $uxon->getProperty('class');
            if (! $class || ! class_exists($class)) {
                throw new DataSheetMapperError($this, 'Invalid data mapper class "' . $class . '"!');
            }
            $mapping = new $class($this, $uxon);
            $this->addMapping($mapping);
        }
        return $this;
    }
    
    /**
     * Map column expressions of the from-sheet to new columns of the to-sheet.
     * 
     * @uxon-property column_to_column_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataColumnMapping[]
     * @uxon-template [{"from": "", "to": ""}]
     * 
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setColumnToColumnMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new DataColumnMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * @deprecated Obsolete! Use setColumnToColumnMappings()
     * This method is only here for UXON backwards compatibility
     */
    protected function setColumnMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        return $this->setColumnToColumnMappings($uxon);
    }
    
    /**
     * Creates filters from the values of a column
     * 
     * @uxon-property column_to_filter_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataColumnToFilterMapping[]
     * @uxon-template [{"from": "", "to": "", "comparator": "="}]
     * 
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setColumnToFilterMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new DataColumnToFilterMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * Create columns from the values of filters
     *
     * @uxon-property filter_to_column_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataFilterToColumnMapping[]
     * @uxon-template [{"from": "", "from_comparator": "", "to": "", "to_single_row": false}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setFilterToColumnMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new DataFilterToColumnMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * Join other data similarly to left/right JOINs in SQL
     *
     * @uxon-property joins
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataJoinMapping[]
     * @uxon-template [{"join": "left", "join_input_data_on_attribute": "", "join_data_sheet_on_attribute": "", "data_sheet": {"object_alias": "", "columns": [{"attribute_alias": ""}], "filters": {"operator": "AND", "conditions": [{"expression": "", comparator: "==", "value": ""}]}}}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setJoins(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new DataJoinMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * Join other data similarly to left/right JOINs in SQL
     *
     * @uxon-property action_to_column_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\ActionToColumnMapping[]
     * @uxon-template [{"from": "", "to": "", "action": {"alias": ""}}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setActionToColumnMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new ActionToColumnMapping($this, $prop));
        }
        return $this;
    }
    
   /**
    * 
    * {@inheritDoc}
    * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getMappings()
    */
    public function getMappings() : array
    {
        return $this->mappings;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::addMapping()
     */
    public function addMapping(DataMappingInterface $mapping) : DataSheetMapperInterface
    {
        $this->mappings[] = $mapping;
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
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setInheritColumns()
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
     * 
     * @return bool
     */
    protected function getInheritColumnsOnlyForSystemAttributes() : bool
    {
        return $this->inheritColumnsOnlySystem;
    }
    
    /**
     * Set to TRUE to inherit only system columns
     * 
     * @uxon-property inherit_columns_only_for_system_attributes
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setInheritColumnsOnlyForSystemAttributes()
     */
    public function setInheritColumnsOnlyForSystemAttributes(bool $value) : DataSheetMapperInterface
    {
        if ($value) {
            if (! $this->canInheritColumns()) {
                throw new DataSheetMapperError($this, 'Data sheets of object "' . $this->getToMetaObject()->getAliasWithNamespace() . '" cannot inherit columns from sheets of "' . $this->getFromMetaObject() . '"!');
            }
            $this->setInheritColumns(true);
        }
        $this->inheritColumnsOnlySystem = $value;
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setInheritFilters()
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
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setInheritSorters()
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
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return DataSheetMapperSchema::class;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getReadMissingFromData() : bool
    {
        return $this->readMissingData;
    }
    
    /**
     * Set to FALSE to disable autoloading missing from-columns from the data source
     * 
     * @uxon-property read_missing_from_data
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return DataSheetMapper
     */
    protected function setReadMissingFromData(bool $value) : DataSheetMapper
    {
        $this->readMissingData = $value;
        return $this;
    }
}