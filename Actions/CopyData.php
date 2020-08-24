<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\DataTypes\DataSheetDataType;

/**
 * Copies all input objects in the input data including dependent objects defined via copy_related_objects.
 * 
 * @author Andrej Kabachnik
 *
 */
class CopyData extends SaveData implements iCreateData
{
  
    private $copyRelatedObjects = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\SaveData::init()
     */
    public function init()
    {
        parent::init();
        $this->setIcon(Icons::CLONE_);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\SaveData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputSheet = $this->getInputDataSheet($task);
        
        $copiedSheets = $this->copyWithRelatedObjects($inputSheet, $this->getCopyRelations(), $transaction);
        
        $copyCounter = $copiedSheets['']->countRows();
        $dependencyCounter = 0;
        foreach ($copiedSheets as $path => $sheet) {
            if ($path === '') {
                continue;
            }
            
            $dependencyCounter += $sheet->countRows();
        }
        
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $message = $this->getResultMessageText() ?? $translator->translate('ACTION.COPYDATA.RESULT', ['%number%' => $copyCounter], $copyCounter) . ' ' . $translator->translate('ACTION.COPYDATA.RESULT_DEPENDENCIES', ['%number%' => $dependencyCounter], $dependencyCounter);
        $result = ResultFactory::createDataResult($task, $inputSheet, $message);
        
        if ($copyCounter > 0) {
            $result->setDataModified(true);
        }
        
        return $result;
    }
    
    /**
     * Copies all instances of the object in the given data sheet as well, as related instances from the passed array of relations.
     * 
     * Returns an array of data sheets with created data. The keys of the array are the relations pathes to the object of the
     * corresponding data sheet. Here is an example copying a meta object with correspoinding attributes and actions: 
     * 
     * - copyWithRelatiodObjects(sheet_of_meta_objects, [relation_to_attributes, relation_to_actons], transaction)
     * will produce the following array
     *
     * [
     *  '': data_sheet_with_copied_meta_objects
     *  relation_to_attributes: data_sheet_with_copied_attributes_of_all_copied_meta_objects
     *  relation_to_actions: data_sheet_with_copied_actions_of_all_copied_meta_objects
     * ]
     * 
     * This method is meant to be used recursively as a copied object may require copying related objects by itself
     * (configured in the model of the relations). Recursive calls should get the relations path of the previous
     * recursion level as parameter. 
     * 
     * @param DataSheetInterface $inputSheet
     * @param array $relationsToCopy
     * @param DataTransactionInterface $transaction
     * @param MetaRelationPathInterface $relationPathFromHeadObject
     * 
     * @throws ActionInputMissingError
     * 
     * @return DataSheetInterface[]
     */
    protected function copyWithRelatedObjects(DataSheetInterface $inputSheet, array $relationsToCopy, DataTransactionInterface $transaction, MetaRelationPathInterface $relationPathFromHeadObject = null) : array
    {
        // Can't copy anything, if there is no UID column (can't load current data)
        if (! $inputSheet->hasUidColumn()) {
            throw new ActionInputMissingError($this, 'Cannot perform action ' . $this->getAliasWithNamespace() . ' on data without a primary key column!');
        }
        
        $result = [];
        
        // Remove all non-attribute columns and those with relations
        foreach ($inputSheet->getColumns() as $col) {
            // Make sure not to remove the UID column
            if ($col === $inputSheet->getUidColumn()) {
                continue;
            }
            // Keep subsheets - they are regular values even if their columns do
            // not point to direct attributes of the main sheet's object.
            if ($col->getDataType() instanceof DataSheetDataType) {
                continue;
            }
            
            if (! $col->isAttribute()) {
                $inputSheet->getColumns()->remove($col);
            } elseif ($col->getAttribute()->isRelated()) {
                $inputSheet->getColumns()->remove($col);
                // FIXME #data-column-name-duplicates-bug For example, columns with aggregators,
                // will be removed, but only one of the two row columns will get removed: MY_ATTRIBUTE_COUNT
                // while MY_ATTRIBUTE:COUNT will remain. When the sheet is copied, the remaining row values
                // will restore the column.
                if ($col->getAttributeAlias() !== $col->getName()) {
                    $inputSheet->removeRowsForColumn($col->getAttributeAlias());
                }
            }
        }
        
        // Make sure, we have all editable attributes (just like in the copy-dialog.
        // Therefore, copy the sheet, add all editable attributes and see if it needs
        // to be read again (if we do that on the input sheet, reading here would override
        // eventually changed values!).
        $currentData = $inputSheet->copy();
        foreach ($inputSheet->getMetaObject()->getAttributes()->getEditable() as $attr) {
            if (! $currentData->getColumns()->getByAttribute($attr)) {
                $currentData->getColumns()->addFromAttribute($attr);
            }
        }
        // Don't read columns with subsheets because they do not represent any editable
        // attributes and reading them here would just cause extra overhead. If there are
        // relations, that need to be copied along with the main object, this is going
        // to be done later in the code.
        foreach ($currentData->getColumns() as $currentCol) {
            if ($currentCol->getDataType() instanceof DataSheetDataType) {
                $currentData->getColumns()->remove($currentCol);
            }
        }
        // Read the data source, if our data is not fresh enough
        if ($currentData->isFresh() === false) {
            $currentData->getFilters()->addConditionFromColumnValues($currentData->getUidColumn());
            $currentData->dataRead();
        }
        
        // Now loop through the sheet with the current data and create copies for each row,
        // including related objects, that should get copied.
        foreach ($currentData->getRows() as $rownr => $row) {
            $rowUid = $currentData->getUidColumn()->getCellValue($rownr);
            
            // Now create a sheet for the new copy of the main object (need a separate sheet, because we will need
            // to remove the UID column, but will still need it's values later on.
            // This sheet will have only one row, which is a merge from current and input data.
            $rowMerged = array_merge($row, $inputSheet->getRow($inputSheet->getUidColumn()->findRowByValue($rowUid)));
            $mainSheet = $currentData
            ->copy()
            ->removeRows()
            ->addRow($rowMerged);
            $mainSheet->getUidColumn()->removeRows();
            // Save the copy of the main object
            $mainSheet->dataCreate(false, $transaction);
            
            if ($relationPathFromHeadObject === null) {
                $relationPathFromHeadObject = RelationPathFactory::createForObject($inputSheet->getMetaObject());
            }
            
            if (! isset($result[$relationPathFromHeadObject->toString()])) {
                $result[$relationPathFromHeadObject->toString()] = $mainSheet;
            } else {
                $result[$relationPathFromHeadObject->toString()]->addRow($mainSheet->getRow(0));
            }
            
            // Now save all related objects and make sure, their relations point to the new (copied) instance.
            // Gather data for the related objects to be copied
            // Need to to this before
            foreach ($relationsToCopy as $rel) {
                if ($rel->isReverseRelation() === false) {
                    throw new ActionRuntimeError($this, 'Cannot copy related object for relation ' . $rel->getAliasWithModifier() . ': only reverse relations currently supported!');    
                }
                
                // If the main sheet has a subsheet for this relation, don't do anything special - the subsheet
                // is what the use wanted or at least saw, so we should not modify this data in any way!
                if ($existingCol = $inputSheet->getColumns()->getByExpression($rel->getAliasWithModifier())) {
                    if ($existingCol->getDataType() instanceof DataSheetDataType) {
                        continue;
                    }
                }
                
                $relRev = $rel->reverse();
                
                // Create a data sheet for the right object of the relation with all it's
                // writable attributes.
                $relSheet = DataSheetFactory::createFromObject($rel->getRightObject());
                foreach ($relSheet->getMetaObject()->getAttributes()->getEditable() as $attr) {
                    $relSheet->getColumns()->addFromAttribute($attr);
                }
                
                // Read data filtered by the left key of the reverse relations. The values for the filter come
                // from the input column, which is the left key of the regular relation.
                $oldLeftKeyValue = $currentData->getColumns()->getByAttribute($rel->getLeftKeyAttribute())->getCellValue($rownr);
                $relSheet->getFilters()->addConditionFromString($relRev->getLeftKeyAttribute()->getAlias(), $oldLeftKeyValue, EXF_COMPARATOR_EQUALS);
                $relSheet->dataRead();
                
                // If there is nothing to be copied, skip to the next relation.
                if ($relSheet->isEmpty() === true) {
                    continue;
                }
                
                // Once the data is read, remove all filters to make sure, there are no links with the original
                // instances
                $relSheet->getFilters()->removeAll();
                
                // Replace keys (left key of the reverse relation) with values from the already copied main sheet
                $newLefKeyValue = $mainSheet->getColumns()->getByAttribute($relRev->getRightKeyAttribute())->getCellValue(0);
                $relSheet->getColumns()->getByAttribute($relRev->getLeftKeyAttribute())->setValues($newLefKeyValue);

                // Call the copy-method for the sheet with the related data (don't forget the relation path)
                $relPath = $relationPathFromHeadObject->copy()->appendRelation($rel);
                $relCopySheets = $this->copyWithRelatedObjects($relSheet, [], $transaction, $relPath);
                
                // The result of the recursive call can be any number of sheets, so we need to merge them
                // with previous results of the foreach().
                foreach ($relCopySheets as $path => $sheet) {
                    // If the relation was not processed yet, just save the sheet. Otherwise, add rows from
                    // the new sheet, to the one existing.
                    if (! isset($result[$path])) {
                        $result[$path] = $sheet;
                    } else {
                        $result[$path]->addRows($sheet->getRows());
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::isUndoable()
     */
    public function isUndoable() : bool
    {
        return false;
    }
    
    /**
     * Returns the the aliases of relations, pointing to the objects, that must be copied
     * together with the main input object.
     * 
     * This array will include those relation specified in `copy_related_objects` as well
     * as those, marked with the property `COPY_WITH_RELATED_OBJECT` in the metamodel.
     * 
     * @return string[]
     */
    protected function getCopyRelationAliases() : array
    {
        $aliases = $this->copyRelatedObjects;
        foreach ($this->getMetaObject()->getRelations() as $rel) {
            if ($rel->isRightObjectToBeCopiedWithLeftObject()) {
                $aliases[] = $rel->getAliasWithModifier(); 
            }
        }
        return array_unique($aliases);
    }
    
    /**
     * Define an array of action aliases, whose right obects should be copied too.
     *
     * @uxon-property copy_related_objects
     * @uxon-type metamodel:relation[]
     * @uxon-template [""]
     *
     * @param UxonObject $relationAliases
     * @return CopyData
     */
    public function setCopyRelatedObjects(UxonObject $relationAliases) : CopyData
    {
        $this->copyRelatedObjects = $relationAliases->toArray();
        return $this;
    }
    
    /**
     * 
     * @return MetaRelationInterface[]
     */
    protected function getCopyRelations() : array
    {
        $rels = [];
        $obj = $this->getMetaObject();
        foreach ($this->getCopyRelationAliases() as $alias) {
            $parsedRelationPath = RelationPath::relationPathParse($alias);
            if ($parsedRelationPath !== false && count($parsedRelationPath) > 1) {
                throw new ActionConfigurationError($this, 'Cannot copy related objects from relation "' . $alias . '": only direct relations supported - no paths!');
            }
            $rels[] = $obj->getRelation($alias);
        }
        return $rels;
    }
}
?>