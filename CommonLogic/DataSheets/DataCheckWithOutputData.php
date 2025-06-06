<?php

namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataSheets\DataCheckFailedError;
use exface\Core\Exceptions\DataSheets\DataCheckRuntimeError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;

/**
 * Functions just like a regular `DataCheck`, with the option of defining an output datasheet, that
 * can be used for further processing.
 * 
 * @see DataCheck
 * @see ChecklistingBehavior
 */
class DataCheckWithOutputData extends DataCheck
{
    private ?string $foreignKeyAttributeAlias = null;

    private ?string $relationStringFromCheckedObject = null;

    private ?UxonObject $outputDataSheetUxon = null;
    private ?DataSheetInterface $outputDataSheet = null;

    public function check(DataSheetInterface $sheet, LogBookInterface $logBook = null): string
    {
        try {
            $result = parent::check($sheet, $logBook);
        } catch (DataCheckFailedError $error) {
            $logBook?->addIndent(1);
            $logBook?->addLine('Generating output sheet...');
            try {
                $outputSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $this->outputDataSheetUxon);
            } catch (\Throwable $e) {
                throw new DataCheckRuntimeError(
                    $sheet,
                    'Cannot generate output datasheet: Data check has missing or invalid UXON property "output_data_sheet"!',
                    null,
                    $e,
                    $this,
                    $error->getRowIndexes());
            }
            
            $rowTemplate = (array)$outputSheet->getRow();
            $outputSheet->removeRows();
            $outputSheet->getColumns()->addFromSystemAttributes();
            
            $badData = $error->getBadData();
            
            // No output data, throw an error with an empty sheet.
            if(!$rowTemplate) {
                $logBook?->addLine('Cannot generate output sheet: No row template found.');
                $logBook?->addIndent(-1);
                $this->outputDataSheet = $outputSheet;
                throw $error;
            }

            $relationPath = $this->getRelationPathFromCheckedObject($sheet->getMetaObject());
            $ownerKeyAttribute = $relationPath->getRelationFirst()->getLeftKeyAttribute();
            $keyColumn = $badData->getColumns()->getByAttribute($ownerKeyAttribute);
            if(! $keyColumn) {
                throw new DataCheckRuntimeError(
                    $sheet,
                    'Cannot generate output data: Missing key attribute "' . $keyColumn->getAttributeAlias() . '"!',
                    null,
                    null,
                    $this,
                    $badData);
            }
            
            if(!$outputSheet->getMetaObject()->hasUidAttribute()) {
                throw new DataCheckRuntimeError(
                    $outputSheet,
                    'Cannot generate output data: Missing UID-Attribute on the MetaObject ('.$outputSheet->getMetaObject()->getAlias().') used for caching!',
                    null,
                    null,
                    $this,
                    $badData);
            }
            
            foreach ($keyColumn->getValues() as $checkedKey) {
                $logBook?->addLine('Adding row for affected item with key "'. $checkedKey .'".');
                $rowTemplate[$this->foreignKeyAttributeAlias] = $checkedKey;
                $outputSheet->addRow($rowTemplate);
            }
            
            $logBook?->addLine('Successfully generated output sheet with '.$outputSheet->countRows().' rows!');
            $logBook?->addIndent(-1);
            $this->outputDataSheet = $outputSheet;
            throw $error;
        }
        
        return $result;
    }

    /**
     * Define the output data that this data check will append to its error message, if it does apply. For every input
     * row  this check applies to, it adds a new row based on this template to the output sheet.
     * 
     * The associated MetaObject must have a UID-Attribute!
     * 
     * NOTE: Auto-suggest does not work for the left-hand side of the `rows` property. If you want to
     * add a custom column to that row, simply write out the attribute alias of that column on the left and the actual
     * value on the right.
     * 
     * @uxon-property output_data_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": "", "rows": [{"CRITICALITY":"0", "LABELS":"", "MESSAGE":"", "COLOR":"","ICON":"sap-icon://message-warning"}]}
     * 
     * @param UxonObject|null $uxon
     * @return $this
     */
    protected function setOutputDataSheet(?UxonObject $uxon) : static
    {
        $this->outputDataSheetUxon = $uxon;
        return $this;
    }

    /**
     * @return UxonObject|null
     */
    public function getOutputDataSheetUxon() : ?UxonObject
    {
        return $this->outputDataSheetUxon;
    }
    
    /**
     * @return DataSheetInterface|null
     */
    public function getOutputDataSheet() : ?DataSheetInterface
    {
        return $this->outputDataSheet;
    }

    /**
     * The relation that points from the object being checked to the object where the output sheet is stored (i.e. the checklist).
     * 
     * For example: If the behavior checks items of DELIVERY_POS  and stores the results as items ALERT, you enter `ALERT`
     * in this property. Of course the two objects must have a relation defined between them. 
     * 
     * @uxon-property relation_from_checked_object_to_checklist
     * @uxon-type metamodel:relation
     * 
     * @param string $relationPath
     * @return DataCheckWithOutputData
     */
    protected function setRelationFromCheckedObjectToChecklist(string $relationPath) : DataCheckWithOutputData
    {
        $this->relationStringFromCheckedObject = $relationPath;
        return $this;
    }

    /**
     * Returns the foreign key of the data object, that points to the checked object.
     * 
     * Consider the following example: DELIVERY_NOTE<-DELIVERY_POS<-ALERT.
     * A ChecklistingBehavior can be used to generate ALERTs whenever any
     * DELIVERY_POS is changed. This method will return the alias of the attribute of
     * ALERT, that contains the foreign key to the DELIVERY_POS.
     * 
     * @param MetaObjectInterface $checkedObject
     * @return string
     */
    public function getForeignKeyAttributeAlias(MetaObjectInterface $checkedObject) : string
    {
        if ($this->foreignKeyAttributeAlias !== null) {
            return $this->foreignKeyAttributeAlias;
        }
        $relPath = $this->getRelationPathFromCheckedObject($checkedObject);
        $this->foreignKeyAttributeAlias = $relPath->getRelationLast()->getRightKeyAttribute()->getAlias();
        return $this->foreignKeyAttributeAlias;
    }

    /**
     * Each checklist item must know, which object it belongs to. The identity of that object will be stored as a foreign key on the 
     * checklist MetaObject in the attribute defined here.
     * 
     * Consider the following example: DELIVERY_NOTE<-DELIVERY_POS<-ALERT.
     * A ChecklistingBehavior can be used to generate ALERTs whenever any
     * DELIVERY_POS is changed. This property will define the alias of the attribute of
     * ALERT, that contains the foreign key to the DELIVERY_POS.
     * 
     * Default is `FOREIGN_KEY`.
     * 
     * @uxon-property foreign_key_attribute_alias
     * @uxon-type string
     * @uxon-default "FOREIGN_KEY"
     *
     * @param string $alias
     * @return $this
     */
    protected function setForeignKeyAttributeAlias(string $alias) : static
    {
        if(!empty($alias)) {
            $this->foreignKeyAttributeAlias = $alias;
        }

        return $this;
    }

    /**
     * @deprecated Use `getForeignKeyAttributeAlias(MetaObjectInterface)` instead.
     * @param MetaObjectInterface $checkedObject
     * @return string
     */
    public function getAffectedUidAlias(MetaObjectInterface $checkedObject) : string
    {
        return $this->getForeignKeyAttributeAlias($checkedObject);
    }

    /**
     * @deprecated Use `setOutputKeyAttributeAlias(string)` instead.
     * @param string $alias
     * @return $this
     */
    protected function setOutputKeyAttributeAlias(string $alias) : static
    {
        return $this->setForeignKeyAttributeAlias($alias);
    }

    /**
     * Returns the relation path from the checked object to the data object
     * 
     * @param MetaObjectInterface $checkedObject
     * @return MetaRelationPathInterface
     */
    public function getRelationPathFromCheckedObject(MetaObjectInterface $checkedObject) : MetaRelationPathInterface
    {
        if(empty($this->relationStringFromCheckedObject)) {
            throw new InvalidArgumentException('Invalid value for property "relation_from_checked_object_to_checklist"! Property must contain a valid relation path.');
        }
        
        return RelationPathFactory::createFromString($checkedObject, $this->relationStringFromCheckedObject);
    }
}