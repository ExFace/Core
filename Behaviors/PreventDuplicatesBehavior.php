<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Behaviors\DataSheetCreateDuplicatesForbiddenError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * Behavior to prevent a creation of a duplicate dataset on create or update Operations.
 * By default an error message will be thrown if a duplicate is found. To change that behavior, the uxon-properties 
 * "on_duplicate_multi_row" and "on_duplicate_single_row" can be set to 'error', 'update', or 'ignore'.
 * If a dataset is a duplicate will be evaluated on the attributes given in the `compare_attributes` uxon property.
 * 
 * Configuration example:
 * 
 * {
 *  "compare_attributes": [
 *      "USER",
 *      "USER_ROLE"
 *  ],
 *  "on_duplicate_multi_row": 'update'
 *  "on_duplicate_single_row": 'error'
 * }
 * 
 */
class PreventDuplicatesBehavior extends AbstractBehavior
{
    const ON_DUPLICATE_ERROR = 'ERROR';
    
    const ON_DUPLICATE_UPDATE = 'UPDATE';
    
    const ON_DUPLICATE_IGNORE = 'IGNORE';
    
    const DUPLICATE_SYSTEM_ATTRIBUTES = 'System_Attributes';
    
    const DUPLICATE_ROW_NUMBER = 'rowNumber';
    
    private $onDuplicateMultiRow = null;
    
    private $onDuplicateSingleRow = null;
    
    private $compareAttributes = [];
    
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
     * @throws DataSheetCreateDuplicatesForbiddenError
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
        $eventRows = $eventSheet->getRows();
        if (empty($duplicates)) {
            return;
        }
        if (count($eventRows) <= 1) {
            if ($this->getOnDuplicateSingleRow() === self::ON_DUPLICATE_IGNORE) {
                $event->preventCreate();
                return;
            } elseif ($this->getOnDuplicateSingleRow() === self::ON_DUPLICATE_UPDATE) {
                $row = $eventSheet->getRow(0);
                $dupl = $duplicates[0];
                foreach ($dupl[self::DUPLICATE_SYSTEM_ATTRIBUTES] as $alias => $value) {
                    $row[$alias] = $value;
                }
                $event->preventCreate();
                $eventSheet->removeRows();
                $eventSheet->addRow($row);
                $eventSheet->dataUpdate();
                return;
            }
        } elseif (count($eventRows) > 1) {
            if ($this->getOnDuplicateMultiRow() === self::ON_DUPLICATE_IGNORE) {
                foreach (array_reverse($duplicates) as $dupl) {
                    // have to reverse array with indices because rows in data sheet get reindexed when one is removed
                    $eventSheet->removeRow($dupl[self::DUPLICATE_ROW_NUMBER]);                    
                }
                return;
            } elseif ($this->getOnDuplicateMultiRow() === self::ON_DUPLICATE_UPDATE) {
                //copy the dataSheet and empty it
                $updateSheet = $eventSheet->copy();
                $updateSheet->removeRows();
                foreach (array_reverse($duplicates) as $dupl) {                    
                    // have to reverse array with indices because rows in data sheet get reindexed when one is removed
                    $row = $eventSheet->getRow($dupl[self::DUPLICATE_ROW_NUMBER]);
                    //copy system attributes values
                    foreach ($dupl[self::DUPLICATE_SYSTEM_ATTRIBUTES] as $alias => $value) {
                        $row[$alias] = $value;
                    }
                    $updateSheet->addRow($row);
                    //delete row from event data sheet
                    $eventSheet->removeRow($dupl[self::DUPLICATE_ROW_NUMBER]);
                }
                //call update on update sheet
                $updateSheet->dataUpdate();
                return;
            }
        }
        $errorMessage = $this->buildDuplicatesErrorMessage($eventSheet, $duplicates);
        throw new DataSheetCreateDuplicatesForbiddenError($eventSheet, $errorMessage, $this->getDuplicateErrorCode());
    }
    
    /**
     * 
     * @param OnBeforeUpdateDataEvent $event
     * @throws DataSheetCreateDuplicatesForbiddenError
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
        
        $duplicatesRowNumbers = $this->getDuplicates($eventSheet);
        if (empty($duplicatesRowNumbers)) {
            return;
        }
        $errorMessage = $this->buildDuplicatesErrorMessage($eventSheet, $duplicatesRowNumbers);
        throw new DataSheetCreateDuplicatesForbiddenError($eventSheet, $errorMessage, $this->getDuplicateErrorCode());
    }
    
    /**
     * Returns array of associative array containing the row number of a duplicate as 'rowNumber' and the uid of the already existing data as 'Uid'.
     * 
     * @param DataSheetInterface $eventSheet
     * @return array
     */
    protected function getDuplicates(DataSheetInterface $eventSheet) : array
    {   
        // check if aliases given in `compare_attributes` actually exist in data sheet, if not dont use it for comparison
        $columns = $eventSheet->getColumns();
        $sheetColumnAliases = [];
        foreach ($columns as $col) {
            $sheetColumnAliases[] = $col->getAttributeAlias();
        }
        $compareAttributes = [];
        foreach ($this->getCompareAttributes() as $attrAlias) {
            if (in_array($attrAlias, $sheetColumnAliases)) {
                $compareAttributes[] = $attrAlias;
            }
        }
        
        $eventRows = $eventSheet->getRows();
        // to get possible duplicates transform every row in event data sheet into a filter for the check sheet
        $checkSheet = $eventSheet->copy();
        $checkSheet->removeRows();
        $orFilterGroup = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_OR, $checkSheet->getMetaObject());
        foreach ($eventRows as $row) {
            $rowFilterGrp = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_AND, $checkSheet->getMetaObject());
            foreach ($compareAttributes as $attrAlias) {
                $dataType = $columns->getByExpression($attrAlias)->getDataType();
                if ($dataType->isValueEmpty($row[$attrAlias]) || $dataType->isValueLogicalNull($row[$attrAlias])) {
                    $rowFilterGrp->addConditionFromString($attrAlias, $row[$attrAlias], ComparatorDataType::EQUALS);
                }
            }
            $orFilterGroup->addNestedGroup($rowFilterGrp);
        }
        $checkSheet->getFilters()->addNestedGroup($orFilterGroup);        
        
        //add system attributes to load
        $checkSheet->getColumns()->addFromSystemAttributes();
        // get data with the applied filters
        $checkSheet->dataRead();
        
        if (empty($checkSheet->getRows())) {
            return [];
        }
        $checkRows = $checkSheet->getRows();
        $duplicates = [];
        for ($i = 0; $i < count($eventRows); $i++) {
            foreach ($checkRows as $chRow) {
                $isDuplicate = true;
                foreach ($this->getCompareAttributes() as $attrAlias) {
                    $dataType = $columns->getByExpression($attrAlias)->getDataType();
                    if ($dataType->parse($eventRows[$i][$attrAlias]) != $dataType->parse($chRow[$attrAlias])) {
                        $isDuplicate = false;
                        break;
                    }
                }
                if ($eventSheet->getMetaObject()->hasUidAttribute()) {
                    //if data sheet has uid column check if the UIDs fit, if so, its not a duplicate, its a normal update (with the same data)
                    $uidattr = $eventSheet->getMetaObject()->getUidAttributeAlias();
                    $col = $columns->getByExpression($uidattr);
                    if ($col !== false) {
                        $dataType = $col->getDataType();
                        if ($dataType->parse($eventRows[$i][$uidattr]) == $dataType->parse($chRow[$uidattr])) {
                            $isDuplicate = false;
                            break;
                        }
                    }
                }
                if ($isDuplicate === true) {
                    $sysAttributes = [];
                    $dupl = [];
                    $dupl[self::DUPLICATE_ROW_NUMBER] = $i;
                    //save system attributes values
                    foreach ($eventSheet->getMetaObject()->getAttributes()->getSystem() as $sys) {
                        $sysAttributes[$sys->getAlias()] = $chRow[$sys->getAlias()];
                    }
                    $dupl[self::DUPLICATE_SYSTEM_ATTRIBUTES] = $sysAttributes;
                    $duplicates[] = $dupl;
                    break;
                }
            }
        }
        
        return $duplicates;
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param array $duplicatesRowNumbers
     * @return string
     */
    protected function buildDuplicatesErrorMessage(DataSheetInterface $dataSheet, array $duplicates) : string
    {
        $object = $dataSheet->getMetaObject();
        $labelAttributeAlias = $object->getLabelAttributeAlias();
        $rows = $dataSheet->getRows();
        $errorRowDescriptor = '';
        $errorMessage = '';
        foreach ($duplicates as $dupl) {
            $index = $dupl[self::DUPLICATE_ROW_NUMBER];
            $row = $rows[$index];
            if ($labelAttributeAlias !== null && $row[$labelAttributeAlias] !== null){
                $errorRowDescriptor .= "'{$row[$labelAttributeAlias]}', ";
            } else {
                $errorRowDescriptor .= strval($index + 1) . ", ";
            }
        }
        $errorRowDescriptor = substr($errorRowDescriptor, 0, -2);
        try {
            $errorMessage = $this->translate('BEHAVIOR.PREVENTDUPLICATEBEHAVIOR.CREATE_DUPLICATES_FORBIDDEN_ERROR', ['%row%' => $errorRowDescriptor, '%object%' => $object->getAlias()]);
        } catch (\Exception $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $errorMessage = 'Can not update/create data, as it contains duplicates of already existing data!';
        }
        return $errorMessage;
    }
    
    /**
     * The attributes determining if a dataset is a duplicate.
     *
     * @uxon-property compare_attributes
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param string[]|UxonObject $arrayOrUxon
     * @return PreventDuplicatesBehavior
     */
    public function setCompareAttributes($arrayOrUxon) : PreventDuplicatesBehavior
    {
        if ($arrayOrUxon instanceof UxonObject) {
            $this->compareAttributes = $arrayOrUxon->toArray();
        } elseif (is_array($arrayOrUxon)) {
            $this->compareAttributes = $arrayOrUxon;
        }
        return $this;
    }
    
    /**
     *
     * @throws BehaviorConfigurationError
     * @return array
     */
    protected function getCompareAttributes() : array
    {
        if (empty($this->compareAttributes)) {
            throw new BehaviorConfigurationError($this->getObject(), "No attributes were set in '{$this->getAlias()}' of the object '{$this->getObject()->getAlias()}' to determine if a dataset is a duplicate or not! Set atleast one attribute via the 'compare_attributes' uxon property!");
        }
        return $this->compareAttributes;
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
     * @uxon-type [error,ignore,update]
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