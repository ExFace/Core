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

/**
 
 */
class PreventDuplicatesBehavior extends AbstractBehavior
{
    private $ignoreDuplicatesInMultiRowOperations = true;
    
    private $ignoreDuplicatesInSingleRowOperations = false;
    
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
        
        $this->setRegistered(true);
        return $this;
    }
    
    /**
     * 
     * @param OnBeforeCreateDataEvent $event
     * @throws BehaviorConfigurationError
     * @throws DataSheetCreateDuplicatesForbiddenError
     */
    public function handleOnBeforeCreate(OnBeforeCreateDataEvent $event)
    {
        if ($this->isDisabled())
            return;
        
        $eventSheet = $event->getDataSheet();
        $object = $eventSheet->getMetaObject();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $object->isExactly($this->getObject())) {
            return;
        }
        
        // check if aliases given in `compare_attributes` actually exist in data sheet, if not throw exception
        $columns = $eventSheet->getColumns();
        $sheetColumnAliases = [];
        foreach ($columns as $col) {
            $sheetColumnAliases[] = $col->getAttributeAlias();
        }
        foreach ($this->getCompareAttributes() as $attrAlias) {
            if (!in_array($attrAlias, $sheetColumnAliases)) {
                throw new BehaviorConfigurationError($this->getObject(), "The alias '{$attrAlias}' given in '{$this->getAlias()}' of object '{$this->getObject()->getAlias()}' is not present in the given data sheet! No check for duplicates is possible!");                
            }
        }
        
        $eventRows = $eventSheet->getRows();
        // to get possible duplicates transform every row in event data sheet into a filter for the check sheet
        $checkSheet = $event->getDataSheet()->copy();
        $checkSheet->removeRows();
        $orFilterGroup = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_OR, $checkSheet->getMetaObject());
        foreach ($eventRows as $row) {
            $rowFilterGrp = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_AND, $checkSheet->getMetaObject());
            foreach ($this->getCompareAttributes() as $attrAlias) {                
                $rowFilterGrp->addConditionFromString($attrAlias, $row[$attrAlias], ComparatorDataType::EQUALS);
            }
            $orFilterGroup->addNestedGroup($rowFilterGrp);
        }        
        $checkSheet->getFilters()->addNestedGroup($orFilterGroup);
        // get data with the applied filters
        $checkSheet->dataRead();
        
        if (!empty($checkSheet->getRows())) {            
            $checkRows = $checkSheet->getRows();
            $duplicatesIdx = [];
            for ($i = 0; $i < count($eventRows); $i++) {
                foreach ($checkRows as $chRow) {
                    $evRowArray = [];
                    $chRowArray = [];
                    foreach ($this->getCompareAttributes() as $attrAlias) {
                        $evRowArray[] = $eventRows[$i][$attrAlias];
                        $chRowArray[] = $chRow[$attrAlias];
                    }
                    if (serialize($evRowArray) === serialize($chRowArray)) {
                        $duplicatesIdx[] = $i;
                    }
                }
            }
            if (count($eventRows) <= 1 && $this->getIgnoreDuplicatesInSingleRowOperations() === true) {
                $eventSheet->removeRow(1);
            } else if (count($eventRows) > 1 && $this->getIgnoreDuplicatesInMultiRowOperations() === true) {
                foreach (array_reverse($duplicatesIdx) as $index) {
                    // have to reverse delete rows because rows in data sheet get reindexed when one is removed
                    $eventSheet->removeRow($index);
                }
            } else {
                $labelAttributeAlias = $object->getLabelAttributeAlias();
                $errorRowDescriptor = '';
                
                foreach ($duplicatesIdx as $index) {
                    $row = $eventRows[$index];
                    if ($labelAttributeAlias !== null && $row[$labelAttributeAlias] !== null){
                        $errorRowDescriptor .= "'{$row[$labelAttributeAlias]}', ";
                    }
                    // if not, just use the position of the crucial datarow in the current selection
                    if ($errorRowDescriptor == ''){
                        $errorRowDescriptor .= strval($index + 1) . ", ";
                    }
                }
                $errorRowDescriptor = substr($errorRowDescriptor, 0, -2);
                throw new DataSheetCreateDuplicatesForbiddenError($eventSheet, $this->translate('BEHAVIOR.PREVENTDUPLICATEBEHAVIOR.CREATE_DUPLICATES_FORBIDDEN_ERROR', ['%row%' => $errorRowDescriptor, '%object%' => $object->getAlias()]), $this->getDuplicateErrorCode());
            }
        }
        return;
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
     * @uxon-property ignore_duplicates_in_multi_row_operations
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return PreventDuplicatesBehavior
     */
    public function setIgnoreDuplicatesInMultiRowOperations (bool $trueOrFalse) : PreventDuplicatesBehavior
    {
        $this->ignoreDuplicatesInMultiRowOperations = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getIgnoreDuplicatesInMultiRowOperations() : bool
    {
        return $this->ignoreDuplicatesInMultiRowOperations;
    }
    
    /**
     * @uxon-property ignore_duplicates_in_single_row_operations
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return PreventDuplicatesBehavior
     */
    public function setIgnoreDuplicatesInSingleRowOperations (bool $trueOrFalse) : PreventDuplicatesBehavior
    {
        $this->ignoreDuplicatesInSingleRowOperations = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getIgnoreDuplicatesInSingleRowOperations() : bool
    {
        return $this->ignoreDuplicatesInSingleRowOperations;
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