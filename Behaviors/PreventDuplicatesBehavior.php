<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Behaviors\DataSheetDuplicatesError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Factories\DataSheetFactory;

/**
 * Behavior to prevent a creation of a duplicate dataset on create or update Operations.
 * 
 * It works by searching data in the data source for matches on `compare_attributes` on
 * every create or update operation. If matches are found, the behavior either throws 
 * an error, ignores the new data or performs an update on the existing data row - depending
 * on the properties `on_duplicate_multi_row` and `on_duplicate_single_row`.
 * 
 * By default an error message will be thrown if a duplicate is found. 
 * 
 * ## Example
 * 
 * Here is how duplicates of `exface.Core.USER_AUTHENTICATOR` core object are prevented.
 * 
 * ```
 * {
 *  "compare_attributes": [
 *      "USER",
 *      "USER_ROLE"
 *  ],
 *  "on_duplicate_multi_row": 'update'
 *  "on_duplicate_single_row": 'error'
 * }
 * 
 * ````
 * 
 */
class PreventDuplicatesBehavior extends AbstractBehavior
{
    const ON_DUPLICATE_ERROR = 'ERROR';
    
    const ON_DUPLICATE_UPDATE = 'UPDATE';
    
    const ON_DUPLICATE_IGNORE = 'IGNORE';
    
    private $onDuplicateMultiRow = null;
    
    private $onDuplicateSingleRow = null;
    
    private $compareAttributeAliases = [];
    
    private $errorCode = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::register()
     */
    public function register() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'handleOnBeforeCreate']);
        
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'handleOnBeforeUpdate']);
        
        $this->setRegistered(true);
        return $this;
    }
    
    /**
     * 
     * @param OnBeforeCreateDataEvent $event
     * @throws DataSheetDuplicatesError
     */
    public function handleOnBeforeCreate(OnBeforeCreateDataEvent $event)
    {
        if ($this->isDisabled()) {
            return ;
        }
        
        $eventSheet = $event->getDataSheet();
        //$eventSheet->getColumns()->set
        $object = $eventSheet->getMetaObject();        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not extended from it.
        if (! $object->isExactly($this->getObject())) {
            return;
        }
        
        $duplicates = $this->getDuplicates($eventSheet);
        
        if (empty($duplicates) === true) {
            return;
        }
        
        if ($eventSheet->countRows() === 1) {
            switch ($this->getOnDuplicateSingleRow()) {
                case self::ON_DUPLICATE_IGNORE:
                    $event->preventCreate();
                    return;
                case self::ON_DUPLICATE_UPDATE:
                    $row = $eventSheet->getRow(0);
                    $duplRows = $duplicates[0];
                    if (count($duplRows) !== 1) {
                        throw new DataSheetDuplicatesError($eventSheet, 'Cannot update duplicates of "' . $this->getObject()->getName() . '" (alias ' . $this->getObject()->getAliasWithNamespace() . '): multiple potential duplicates found!');
                    }
                    foreach ($eventSheet->getMetaObject()->getAttributes()->getSystem() as $systemAttr) {
                        $row[$systemAttr->getAlias()] = $duplRows[0][$systemAttr->getAlias()];
                    }
                    $event->preventCreate();
                    $eventSheet->removeRows();
                    $eventSheet->addRow($row);
                    $eventSheet->dataUpdate();
                    return;
            }
        } else {
            switch ($this->getOnDuplicateMultiRow()) {
                case self::ON_DUPLICATE_IGNORE:
                    $duplRowNos = array_keys($duplicates);
                    foreach (array_reverse($duplRowNos) as $duplRowNo) {
                        // have to reverse array with indices because rows in data sheet get reindexed when one is removed
                        $eventSheet->removeRow($duplRowNo);                    
                    }
                    
                    if ($eventSheet->isEmpty()) {
                        $event->preventCreate();
                    }
                    
                    return;
                case self::ON_DUPLICATE_UPDATE:
                    //copy the dataSheet and empty it
                    $updateSheet = $eventSheet->copy();
                    $updateSheet->removeRows();
                    $duplRowNos = array_keys($duplicates);
                    foreach (array_reverse($duplRowNos) as $duplRowNo) {                    
                        // have to reverse array with indices because rows in data sheet get reindexed when one is removed
                        $row = $eventSheet->getRow($duplRowNo);
                        $duplRows = $duplicates[$duplRowNo];
                        if (count($duplRows) !== 1) {
                            throw new DataSheetDuplicatesError($eventSheet, 'Cannot update duplicates of "' . $this->getObject()->getName() . '" (alias ' . $this->getObject()->getAliasWithNamespace() . '): multiple potential duplicates found!');
                        }
                        //copy system attributes values
                        foreach ($eventSheet->getMetaObject()->getAttributes()->getSystem() as $systemAttr) {
                            $row[$systemAttr->getAlias()] = $duplRows[0][$systemAttr->getAlias()];
                        }
                        $updateSheet->addRow($row);
                        //delete row from event data sheet
                        $eventSheet->removeRow($duplRowNo);
                    }
                    //call update on update sheet
                    $updateSheet->dataUpdate();
                    
                    if ($eventSheet->isEmpty()) {
                        $event->preventCreate();
                    }
                    
                    return;
            }
        }
        
        $errorMessage = $this->createDuplicatesErrorMessage($eventSheet, $duplicates);
        throw new DataSheetDuplicatesError($eventSheet, $errorMessage, $this->getDuplicateErrorCode());
    }
    
    /**
     * 
     * @param OnBeforeUpdateDataEvent $event
     * @throws DataSheetDuplicatesError
     */
    public function handleOnBeforeUpdate(OnBeforeUpdateDataEvent $event)
    {
        if ($this->isDisabled()) {
            return ;
        }
        
        $eventSheet = $event->getDataSheet();
        $object = $eventSheet->getMetaObject();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not extended from it.
        if (! $object->isExactly($this->getObject())) {
            return;
        }
        
        // Ignore partial updates, that do not change compared attributes
        $foundCompareCols = false;
        foreach ($this->getCompareAttributeAliases() as $attrAlias) {
            if ($eventSheet->getColumns()->getByAttribute($object->getAttribute($attrAlias))) {
                $foundCompareCols = true;
                break;
            }
        }
        if ($foundCompareCols === false) {
            return;
        } 
        
        $duplicates = $this->getDuplicates($eventSheet);
        if (empty($duplicates)) {
            return;
        }
        $errorMessage = $this->createDuplicatesErrorMessage($eventSheet, $duplicates);
        throw new DataSheetDuplicatesError($eventSheet, $errorMessage, $this->getDuplicateErrorCode());
    }
    
    /**
     * Returns array of associative array with original row numbers for keys and arrays of potential duplicate rows as values
     * 
     * The array has the following format:
     * [
     *  <eventDataRowNumber> => [
     *      <duplicateDataRow1Array>,
     *      <duplicateDataRow2Array>,
     *      ...
     *  ]
     * ]
     * 
     * @param DataSheetInterface $eventSheet
     * @return array
     */
    protected function getDuplicates(DataSheetInterface $eventSheet) : array
    {   
        $eventDataCols = $eventSheet->getColumns();
        
        $compareCols = [];
        $missingCols = [];
        $missingAttrs = [];
        foreach ($this->getCompareAttributeAliases() as $attrAlias) {
            $attr = $this->getObject()->getAttribute($attrAlias);
            if ($col = $eventDataCols->getByAttribute($attr)) {
                $compareCols[] = $col;
            } else {
                $missingAttrs[] = $attr;
            }
        }
        
        $eventRows = $eventSheet->getRows();
        
        if (empty($missingAttrs) === false) {
            if ($eventSheet->hasUidColumn(true) === false) {
                throw new BehaviorRuntimeError($this->getObject(), 'Cannot check for duplicates of "' . $this->getObject()->getName() . '" (alias ' . $this->getObject()->getAliasWithNamespace() . '): not enough data!');
            } else {
                $missingAttrSheet = DataSheetFactory::createFromObject($this->getObject());
                $missingAttrSheet->getFilters()->addConditionFromColumnValues($eventSheet->getUidColumn());
                $missingCols = [];
                foreach ($missingAttrs as $attr) {
                    $missingCols[] = $missingAttrSheet->getColumns()->addFromAttribute($attr);
                }
                $missingAttrSheet->dataRead();
                
                $uidColName = $eventSheet->getUidColumnName();
                foreach ($eventRows as $rowNo => $row) {
                    foreach ($missingCols as $missingCol) {
                        $eventRows[$rowNo][$missingCol->getName()] = $missingCol->getValueByUid($row[$uidColName]);
                    }
                }
            }
        }
        
        // Create a data sheet to search for possible duplicates
        $checkSheet = $eventSheet->copy();
        $checkSheet->removeRows();
        
        // Add columns even for attributes that are not present in the original event sheet
        foreach ($missingAttrs as $attr) {
            $checkSheet->getColumns()->addFromAttribute($attr);
        }
        
        // Add system attributes in case we are going to update
        $checkSheet->getColumns()->addFromSystemAttributes();
        
        // To get possible duplicates transform every row in event data sheet into a filter for 
        // the check sheet
        $orFilterGroup = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_OR, $checkSheet->getMetaObject());
        foreach ($eventRows as $rowNo => $row) {
            $rowFilterGrp = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_AND, $checkSheet->getMetaObject());
            foreach (array_merge($compareCols, $missingCols) as $col) {
                $dataType = $col->getDataType();
                $value = $row[$col->getName()];
                if ($value === null || $value === '') {
                    throw new BehaviorRuntimeError($this->getObject(), 'Cannot check for duplicates for "' . $this->getObject()->getName() . '" (alias ' . $this->getObject()->getAliasWithNamespace() . '): no input data found for attribute "' . $col->getAttributeAlias() . '"!');
                }
                $rowFilterGrp->addConditionFromString($col->getAttributeAlias(), $value, ComparatorDataType::EQUALS);
            }
            $orFilterGroup->addNestedGroup($rowFilterGrp);
        }
        $checkSheet->getFilters()->addNestedGroup($orFilterGroup);        
        
        // Read the data with the applied filters
        $checkSheet->dataRead();
        
        $checkRows = $checkSheet->getRows();
        
        if (empty($checkRows)) {
            return [];
        }
        
        $duplicates = [];
        for ($eventRowNo = 0; $eventRowNo < count($eventRows); $eventRowNo++) {
            foreach ($checkRows as $chRow) {
                $isDuplicate = true;
                foreach ($compareCols as $col) {
                    $dataType = $col->getDataType();
                    $key = $col->getName();
                    if ($dataType->parse($eventRows[$eventRowNo][$key]) != $dataType->parse($chRow[$key])) {
                        $isDuplicate = false;
                        break;
                    }
                }
                if ($eventSheet->hasUidColumn(true)) {
                    $col = $eventSheet->getUidColumn();
                    $dataType = $col->getDataType();
                    $key = $col->getName();
                    if ($dataType->parse($eventRows[$eventRowNo][$key]) == $dataType->parse($chRow[$key])) {
                        $isDuplicate = false;
                        break;
                    }
                }
                if ($isDuplicate === true) {
                    $duplicates[$eventRowNo][] = $chRow;
                    break;
                }
            }
        }
        
        return $duplicates;
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param array[] $duplicates
     * @return string
     */
    protected function createDuplicatesErrorMessage(DataSheetInterface $dataSheet, array $duplicates) : string
    {
        $object = $dataSheet->getMetaObject();
        $labelAttributeAlias = $object->getLabelAttributeAlias();
        $rows = $dataSheet->getRows();
        $errorRowDescriptor = '';
        $errorMessage = '';
        foreach (array_keys($duplicates) as $duplRowNo) {
            $row = $rows[$duplRowNo];
            if ($labelAttributeAlias !== null && $row[$labelAttributeAlias] !== null){
                $errorRowDescriptor .= "'{$row[$labelAttributeAlias]}', ";
            } else {
                $errorRowDescriptor .= strval($duplRowNo + 1) . ", ";
            }
        }
        $errorRowDescriptor = substr($errorRowDescriptor, 0, -2);
        try {
            $errorMessage = $this->translate('BEHAVIOR.PREVENTDUPLICATEBEHAVIOR.CREATE_DUPLICATES_FORBIDDEN_ERROR', ['%row%' => $errorRowDescriptor, '%object%' => '"' . $object->getName() . '" (' . $object->getAliasWithNamespace() . ')']);
        } catch (\Exception $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $errorMessage = 'Cannot update/create data, as it contains duplicates of already existing data!';
        }
        return $errorMessage;
    }
    
    /**
     * The attributes determining if a dataset is a duplicate.
     *
     * @uxon-property compare_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     *
     * @param string[]|UxonObject $arrayOrUxon
     * @return PreventDuplicatesBehavior
     */
    public function setCompareAttributes($arrayOrUxon) : PreventDuplicatesBehavior
    {
        if ($arrayOrUxon instanceof UxonObject) {
            $this->compareAttributeAliases = $arrayOrUxon->toArray();
        } elseif (is_array($arrayOrUxon)) {
            $this->compareAttributeAliases = $arrayOrUxon;
        }
        return $this;
    }
    
    /**
     *
     * @throws BehaviorConfigurationError
     * @return string[]
     */
    protected function getCompareAttributeAliases() : array
    {
        if (empty($this->compareAttributeAliases)) {
            throw new BehaviorConfigurationError($this->getObject(), "No attributes were set in '{$this->getAlias()}' of the object '{$this->getObject()->getAlias()}' to determine if a dataset is a duplicate or not! Set atleast one attribute via the 'compare_attributes' uxon property!");
        }
        return $this->compareAttributeAliases;
    }
    
    /**
     * Set what should happen if a duplicate is found in a multi row create operation.
     * To ignore duplicates set it to ´ignore´.
     * To update the existing duplicate data row instead of creating a new one set it to ´update´.
     * To show an error when duplicate is found set it to ´error´, that is the default behavior.
     * 
     * @uxon-property on_duplicate_multi_row
     * @uxon-type [error,ignore,update]
     * 
     * 
     * @param string $value
     * @return PreventDuplicatesBehavior
     */
    public function setOnDuplicateMultiRow (string $value) : PreventDuplicatesBehavior
    {
        $value = mb_strtoupper($value);
        if (defined(__CLASS__ . '::ON_DUPLICATE_' . $value)) {
            $this->onDuplicateMultiRow = $value;
        } else {
            throw new WidgetPropertyInvalidValueError('Invalid behavior on duplicates "' . $value . '". Only ERROR, IGNORE and UPDATE are allowed!', '6TA2Y6A');
        }
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getOnDuplicateMultiRow() : string
    {
        if ($this->onDuplicateMultiRow !== null) {
            return $this->onDuplicateMultiRow;
        }
        return self::ON_DUPLICATE_ERROR;
    }
    
    /**
     * Set what should happen if a duplicate is found in a single row create operation.
     * To ignore duplicates set it to ´ignore´.
     * To update the existing duplicate data row instead of creating a new one set it to ´update´.
     * To show an error when duplicate is found set it to ´error´, that is the default behavior.
     * @uxon-property on_duplicate_single_row
     * @uxon-type [error,ignore,update]
     * 
     * @param string $value
     * @return PreventDuplicatesBehavior
     */
    public function setOnDuplicateSingleRow(string $value) : PreventDuplicatesBehavior
    {
        $value = mb_strtoupper($value);
        if (defined(__CLASS__ . '::ON_DUPLICATE_' . $value)) {
            $this->onDuplicateSingleRow = $value;
        } else {
            throw new WidgetPropertyInvalidValueError('Invalid behavior on duplicates "' . $value . '". Only ERROR, IGNORE and UPDATE are allowed!', '6TA2Y6A');
        }
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getOnDuplicateSingleRow() : string
    {
        if ($this->onDuplicateSingleRow !== null) {
            return $this->onDuplicateSingleRow;
        }
        return self::ON_DUPLICATE_ERROR;
    }
    
    /**
     * @uxon-property duplicate_error_code
     * @uxon-type string
     * 
     * @param string $code
     * @return PreventDuplicatesBehavior
     */
    public function setDuplicateErrorCode (string $code) : PreventDuplicatesBehavior
    {
        $this->errorCode = $code;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getDuplicateErrorCode() : ?string
    {
        return $this->errorCode;
    }
    
    /**
     * 
     * @param string $messageId
     * @param array $placeholderValues
     * @param float $pluralNumber
     * @return string
     */
    protected function translate(string $messageId, array $placeholderValues = null, float $pluralNumber = null) : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate($messageId, $placeholderValues, $pluralNumber);
    }
}