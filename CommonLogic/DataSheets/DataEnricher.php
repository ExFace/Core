<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\DataSheets\DataMapperRuntimeError;
use exface\Core\Interfaces\Debug\DataLogBookInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;

/**
 * 
 * 
 * @see DataSheetMapperInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataEnricher 
{
    private $object = null;
    
    private $logbook = null;
    
    private $readMissingData = true;
    
    private $keepColsAddedByFormulas = false;
    
    private $ignoreColsIfCannotRead = false;
    
    private $expressions = [];
    
    public function __construct(MetaObjectInterface $object, DataLogBookInterface $logbook = null)
    {
        $this->object = $object;
        $this->logbook = $logbook;
    }
    
    public function enrich(DataSheetInterface $dataSheet) : DataSheetInterface
    {
        if ($dataSheet->hasUidColumn(true) && $dataSheet->isFresh()) {
            $enriched = $this->enrichPerUID($dataSheet);
        } else {
            $enriched = $this->enrichWithoutUIDs($dataSheet);
        } // END if($dataSheet->hasUidColumn(true) && $dataSheet->isFresh())
            
        return $enriched;
    }
    
    public function getMetaObject() : MetaObjectInterface
    {
        return $this->object;
    }
    
    public function addExpression($expressionOrString) : DataEnricher
    {
        if (! ($expressionOrString instanceof ExpressionInterface)) {
            $this->expressions[] = ExpressionFactory::createForObject($this->object, $expressionOrString);
        } else {
            $this->expressions[] = $expressionOrString;
        }
        return $this;
    }
    
    public function setIncludeColumnsAddedAutomatically(bool $trueOrFalse) : DataEnricher
    {
        $this->keepColsAddedByFormulas;
        return $this;
    }
    
    protected function getRequiredExpressions() : array
    {
        return $this->expressions;
    }
    
    protected function enrichPerUID(DataSheetInterface $dataSheet) : DataSheetInterface
    {
        $logbook = $this->logbook;
        $additionSheet = null;
        // See if any required columns are missing in the original data sheet. If so, add empty
        // columns and also create a separate sheet for reading missing data.
        $addedCols = [];
        foreach ($this->getRequiredExpressions() as $expr) {
            if ($dataSheet->getColumns()->getByExpression($expr)){
                continue;
            }
            if ($additionSheet === null) {
                $additionSheet = $dataSheet->copy();
                foreach ($additionSheet->getColumns() as $col) {
                    if ($col !== $additionSheet->getUidColumn()) {
                        $additionSheet->getColumns()->remove($col);
                    }
                }
            }
            $dataSheet->getColumns()->addFromExpression($expr);
            $addedCols[] = $additionSheet->getColumns()->addFromExpression($expr);
        }
        if ($logbook !== null) {
            $logbook->addLine('Found ' . count($addedCols) . ' columns to read for the mapper', 1);
        }
        
        // If columns were added to the original sheet, that need data to be loaded,
        // use the additional data sheet to load the data. This makes sure, the values
        // in the original sheet (= the input values) are not overwrittten by the read
        // operation.
        if (! $dataSheet->isFresh() && $this->getReadMissingFromData() === true){
            $additionSheet->getFilters()->addConditionFromColumnValues($dataSheet->getUidColumn());
            $additionSheet->dataRead();
            if ($logbook !== null) {
                $logbook->addLine('Read ' . $additionSheet->countRows() . ' rows filtered by ' . $dataSheet->getUidColumn()->getName(), 1);
            }
            $uidCol = $dataSheet->getUidColumn();
            $uidColName = $uidCol->getName();
            foreach ($additionSheet->getColumns() as $addedCol) {
                foreach ($additionSheet->getRows() as $row) {
                    $uid = $row[$uidColName];
                    $rowNo = $uidCol->findRowByValue($uid);
                    if ($uid === null || $rowNo === false) {
                        throw new DataMapperRuntimeError($this, $dataSheet, 'Cannot load additional data in preparation for mapping!');
                    }
                    // Only set cell values if the column is an added column
                    // or the column does not exist yet in the original data sheet.
                    // It is important to check both because formula might lead to more columns being added.
                    if (in_array($addedCol, $addedCols, true) || $dataSheet->getColumns()->getByExpression($addedCol->getExpressionObj()) === FALSE) {
                        $dataSheet->setCellValue($addedCol->getName(), $rowNo, $row[$addedCol->getName()]);
                    }
                }
            }
        }
        return $dataSheet;
    }
    
    protected function getReadMissingFromData() : bool
    {
        return $this->readMissingData;
    }
    
    protected function enrichWithoutUIDs(DataSheetInterface $dataSheet) : DataSheetInterface
    {
        $logbook = $this->logbook;
        // No UIDs or not fresh
        // See if any attributes required for the missing columns are related in the way described above
        // the if(). If so, load the data separately and put it into the from-sheet. This is mainly usefull
        // for formulas.
        $baseObj = $this->getMetaObject();
        foreach ($this->getRequiredExpressions() as $expr) {
            if ($dataSheet->getColumns()->getByExpression($expr)) {
                continue;
            }
            foreach ($expr->getRequiredAttributes() as $reqAlias) {
                // Only process requried attribute aliases, that are not present as columns yet and
                // have a non-empty relation path consisting only of forward relations
                if ($dataSheet->getColumns()->getByExpression($reqAlias)) {
                    continue;
                }
                $reqAttr = $baseObj->getAttribute($reqAlias);
                $reqRelPath = $reqAttr->getRelationPath();
                if ($reqRelPath->isEmpty()) {
                    continue;
                }
                // Find the last relation in the path, where there is a key column with values
                // in the current data.
                $reqRelKeyCol = null;
                $reqRelKeyColPath = null;
                $reqRelColPath = RelationPathFactory::createForObject($baseObj);
                $reqRelForwardOnly = true;
                foreach ($reqRelPath->getRelations() as $reqRel) {
                    if ($reqRel->isForwardRelation()) {
                        $reqRelColPath = $reqRelColPath->appendRelation($reqRel);
                        if (($keyCol = $dataSheet->getColumns()->getByExpression($reqRelColPath->toString())) && $keyCol->isEmpty(true) === false) {
                            $reqRelKeyCol = $keyCol;
                            $reqRelKeyColPath = $reqRelColPath;
                        }
                    } else {
                        // If there are backwards-relations in the path, jus skip the whole thing,
                        // maybe some other parts of the code will deal with it.
                        $reqRelForwardOnly = false;
                        break;
                    }
                }
                // If we have found a target, read data for it
                // IDEA collect all missing data based on the same object and read it at once instead of
                // reading data for each missing column separately.
                if ($reqRelForwardOnly === true && $reqRelKeyCol !== null) {
                    $targetCol = $dataSheet->getColumns()->addFromExpression($reqAlias);
                    $reqRelSheet = DataSheetFactory::createFromObject($reqRelKeyColPath->getEndObject());
                    $valCol = $reqRelSheet->getColumns()->addFromExpression(ExpressionFactory::createForObject($baseObj, $reqAlias)->rebase($reqRelKeyColPath->toString()));
                    $keyCol = $reqRelSheet->getColumns()->addFromAttribute($reqRelKeyColPath->getRelationLast()->getRightKeyAttribute());
                    $reqRelSheet->getFilters()->addConditionFromValueArray($reqRelKeyColPath->getRelationLast()->getRightKeyAttribute()->getAliasWithRelationPath(), $reqRelKeyCol->getValues(), ComparatorDataType::IN);
                    $reqRelSheet->dataRead();
                    foreach ($reqRelKeyCol->getValues() as $fromRowIdx => $key) {
                        $targetCol->setValue($fromRowIdx, $valCol->getValue($keyCol->findRowByValue($key)));
                    }
                    if ($logbook !== null) {
                        $logbook->addLine('Read ' . $reqRelSheet->countRows() . ' rows for columns related to mapped data (object "' . $reqRelSheet->getMetaObject()->getAliasWithNamespace() . '")', 1);
                    }
                }
                
            } // END foreach ($expr->getRequiredAttributes())
        } // END foreach ($this->getRequiredExpressions())
        return $dataSheet;
    }
    
    
}