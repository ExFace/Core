<?php
namespace exface\Core\CommonLogic\PWA;

use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\PWA\PWAInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\PWA\PWADatasetInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Behaviors\TimeStampingBehavior;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\ImageUrlDataType;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;

class PWADataset implements PWADatasetInterface
{
    use ImportUxonObjectTrait;
    
    private $pwa = null;
    
    private $dataSheet = null;
    
    private $actions =  [];
    
    private $uid = null;

    private array $columnsByIncremental = [
        'incremental' => null,
        'notIncremental' => null
    ];

    private bool $forceIncremental = false;

    private ?bool $isIncremental = null;

    /**
     * 
     * @param PWAInterface $pwa
     * @param DataSheetInterface $dataSheet
     * @param string $uid
     */
    public function __construct(PWAInterface $pwa, DataSheetInterface $dataSheet, string $uid = null)
    {
        $this->pwa = $pwa;
        $this->dataSheet = $dataSheet;
        $this->uid = $uid;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject(): UxonObject
    {
        $arr = [];
        $arr['object_alias'] = $this->getMetaObject()->getAliasWithNamespace();
        $arr['has_uid_columns'] = $this->dataSheet->getUidColumn() !== null;
        $arr['incremental_attribute'] = $this->getIncrementAttribute()?->getAlias();
        $arr['column_count'] = ($this->getDataSheet()->getColumns())->count();
        $arr['estimated_rows'] = $this->estimateRows();

        if ($this->isIncremental === null){
            $this->isIncremental();
        }

        if (key_exists('columns', $this->columnsByIncremental)) {
            $cols = [];
            foreach ($this->columnsByIncremental['columns'] as $col) {
                $colArray = $col->exportUxonObject()->toArray();
                $colArray['increment_attribute'] = (($col->getMetaObject())
                    ->getBehaviors()->getByPrototypeClass(TimeStampingBehavior::class)->getFirst())
                    ?->getUpdatedOnAttribute();
            }

            if (empty($cols) === false) {
                $arr['incremental_columns'] = $cols;
            }
        } else {
            $cols = [];
            foreach ($this->getIncrementalColumns() as $col) {
                $cols[] = $col->exportUxonObject()->toArray();
            }

            if (empty($cols) === false) {
                $arr['incremental_columns'] = $cols;
            }

            $cols = [];
            foreach ($this->getNonIncrementalColumns() as $col) {
                $cols[] = $col->exportUxonObject()->toArray();
            }

            if (empty($cols) === false) {
                $arr['non_incremental_columns'] = $cols;
            }
        }

        return new UxonObject($arr);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWARouteInterface::getPWA()
     */
    public function getPWA(): PWAInterface
    {
        return $this->pwa;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::getDataSheet()
     */
    public function getDataSheet(): DataSheetInterface
    {
        return $this->dataSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::canInclude()
     */
    public function canInclude(DataSheetInterface $dataSheet) : bool
    {
        if (! $this->getMetaObject()->isExactly($dataSheet->getMetaObject())) {
            return false;
        }
        $thisSheet = $this->getDataSheet();
        if ($thisSheet->hasAggregations() && $dataSheet->hasAggregations()) {
            foreach ($dataSheet->hasAggregations() as $a => $aggr) {
                if ($thisSheet->getAggregations()->get($a)->getAttributeAlias() !== $aggr->getAttributeAlias()) {
                    return false;
                }
            }
            return true;
        }
        if ($thisSheet->hasAggregateAll() !== $dataSheet->hasAggregateAll()) {
            return false;
        }
        // TODO compare filters too!!!
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::includeData()
     */
    public function includeData(DataSheetInterface $anotherSheet) : PWADatasetInterface
    {
        if (! $this->getDataSheet()->getMetaObject()->isExactly($anotherSheet->getMetaObject())) {
            throw new RuntimeException('Cannot include data in offline data set: object mismatch!');
        }
        
        $setSheet = $this->getDataSheet();
        $setCols = $setSheet->getColumns();
        foreach ($anotherSheet->getColumns() as $col) {
            if (! $setCols->getByExpression($col->getExpressionObj())) {
                $setCols->addFromExpression($col->getExpressionObj());
            }
        }
        foreach ($anotherSheet->getFilters()->getConditionsRecursive() as $cond) {
            $setSheet->getColumns()->addFromExpression($cond->getExpression());
        }
        
        return $this;
    }

    public function getMetaObject(): MetaObjectInterface
    {
        return $this->dataSheet->getMetaObject();
    }
    
    public function addAction(ActionInterface $action) : PWADatasetInterface
    {
        $this->actions[] = $action;
        return $this;
    }
    
    /**
     * 
     * @return ActionInterface[]
     */
    public function getActions() : array
    {
        return $this->actions;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::getUid()
     */
    public function getUid() : ?string
    {
        return $this->uid;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::setUid()
     */
    public function setUid(string $uid) : PWADatasetInterface
    {
        $this->uid = $uid;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::estimateRows()
     */
    public function estimateRows() : ?int
    {
        return $this->getDataSheet()->copy()->setAutoCount(true)->countRowsInDataSource();
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::readData()
     */
    public function readData(int $limit = null, int $offset = null, string $incrementValue = null) : DataSheetInterface
    {
        $ds = $this->getDataSheet()->copy();

        if ($incrementValue !== null && null !== $incrementAttr = $this->getIncrementAttribute()) {
            // load only data since last increment
            $conditions = new ConditionGroup($ds->getWorkbench(), EXF_LOGICAL_OR, $ds->getMetaObject());
            $conditions->addConditionFromAttribute(
                $incrementAttr,
                $incrementValue,
                ComparatorDataType::GREATER_THAN_OR_EQUALS);

            // check relations to find changes there even if the parent sheet was not updated
            // only one of the relation objects has to be newer then last increment for us to load the data again
            $incrementAttributeAliases = $this->getRelationAttributesToFilter($ds, $incrementAttr, $incrementValue);
            $relatedObjectsConditionGroup = new ConditionGroup($ds->getWorkbench(), EXF_LOGICAL_OR, $ds->getMetaObject());
            foreach ($incrementAttributeAliases as $alias)
            {
                $relatedObjectsConditionGroup->addConditionFromString(
                    $alias,
                    $incrementValue,
                    ComparatorDataType::GREATER_THAN_OR_EQUALS);
            }

            $conditions->addNestedGroup($relatedObjectsConditionGroup);
            $ds->getFilters()->addNestedGroup($conditions);
        }

        $ds->dataRead($limit, $offset);
        return $ds;
    }

    /**
     * Finds all relation objects, that also has the increment attribute and returns the relation path for it
     *
     * e.g. Artikel__Hersteller__Kuerzel -> Hersteller
     * Increment: ZeitAend
     * Filter: Artikel__Hersteller__ZeitAend
     *
     * @param DataSheetInterface $datasheet
     * @param MetaAttributeInterface|null $incrementAttribute
     * @param string $incrementValue
     * @return array
     */
    public function getRelationAttributesToFilter(
        DataSheetInterface $datasheet, ?MetaAttributeInterface $incrementAttribute): array
    {
        $processedRelations = [];
        $relationIncrementAttributeAliases = [];
        $incrementAttributeAlias = $incrementAttribute->getAlias();
        foreach ($datasheet->getColumns() as $column) {
            // only add if increment attribute present
            if ($column->isAttribute()
                && $this->isIncrementalRelationColumn($datasheet->getMetaobject(), $column, $incrementAttributeAlias)) {
                // build full relation path with parent object
                $relationIncrementObjectAlias = $column->getAttribute()->getRelationPath()->toString();

                // build filter for each object in relation path that has not yet been processed
                while(str_contains($relationIncrementObjectAlias, RelationPath::RELATION_SEPARATOR)) {
                    $relationPath = RelationPath::join($relationIncrementObjectAlias, $incrementAttributeAlias);
                    // only add new relation objects
                    if (in_array($relationPath, $processedRelations) === false){
                        $relationIncrementAttributeAliases[] = $relationPath;
                        $processedRelations[] = $relationIncrementObjectAlias;
                    }

                    // LagerLE__LagerQuant__Artikelstamm --> LagerLE__LagerQuant  --> LagerLE
                    $indexOfLastSeparator = strrpos($relationIncrementObjectAlias,RelationPath::RELATION_SEPARATOR);
                    $lastRelationInChain = substr($relationIncrementObjectAlias, $indexOfLastSeparator);
                    $relationIncrementObjectAlias = str_replace($lastRelationInChain, '', $relationIncrementObjectAlias);
                }

                // also add direct paths
                if (in_array($relationIncrementObjectAlias, $processedRelations) === false){
                    $relationPath = RelationPath::join($relationIncrementObjectAlias, $incrementAttributeAlias);
                    $relationIncrementAttributeAliases[] = $relationPath;
                    $processedRelations[] = $relationIncrementObjectAlias;
                }
            }
        }

        return $relationIncrementAttributeAliases;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::isIncremental()
     */
    public function isIncremental() : bool
    {
        $isIncremental = $this->isIncremental;
        if ($isIncremental !== null) {
            return $this->isIncremental;
        }

        $isIncremental = true;
        $incrementalAttribute =  $this->getIncrementAttribute();
        switch (true) {
            case $incrementalAttribute === null:
                $this->columnsByIncremental['columns'] = $this->getDataSheet()->getColumns()->getAll();
                $isIncremental = false;
                break;
            case $this->dataSheet->getUidColumn() === null:
                $isIncremental = false;
            // has only incremental relations
            default:
                $incrementAttributeAlias = $incrementalAttribute->getAlias();
                foreach ($this->dataSheet->getColumns() as $column) {
                    if ($column->isAttribute()
                        && $column->getAttribute()->getRelationPath()->isEmpty() === false
                        && $this->isIncrementalRelationColumn(
                            $this->dataSheet->getMetaObject(), $column, $incrementAttributeAlias) === false) {
                        $this->columnsByIncremental['notIncremental'][] = $column;
                        $isIncremental = false;
                    } else {
                        $this->columnsByIncremental['incremental'][] = $column;
                    }
                }
        }

        $this->isIncremental = $this->forceIncremental ? true : $isIncremental;
        return $this->isIncremental;
    }

    public function getIncrementalColumns() : ?array
    {
        return $this->columnsByIncremental['incremental'];
    }

    public function getNonIncrementalColumns() : ?array
    {
        return $this->columnsByIncremental['notIncremental'];
    }

    /**
     * 
     * Configure if a dataset should be forced to be incrementally synchronized.
     * This means all columns in the datasheet that cannot be requested incremental
     * will not be recognized when searching for changes!
     *
     * @uxon-proeprty: force_incremental_sync
     * @param bool $forced
     * @return PWADatasetInterface
     */
    public function forceIncrementalSync(bool $forced) : PWADatasetInterface
    {
        $this->forceIncremental = $forced;
        return $this;
    }

    /**
     *  Check all necessary properties of the column to see if it is incremental.
     *
     *  e.g. only forward relations [$ds: Lagerort, $colAttr: Lagerbereich__Lager__Name]
     *  Filter:
     *  - Lagerbereich__Lager__ZeitAend
     *  - Lagerbereich__ZeitAend
     *  - ZeitAend
     *  --> Incremental ✓
     *
     *  e.g.  1-1 relation to a View. [$ds: Lagerplatz, $colAttr: Lagerplatzliste__Lager__Name]
     *
     * Filter:
     *  - Lagerplatzliste__Lager__ZeitAend*
     *  - ZeitAend
     *
     *  *Lagerplatzliste on itself has no ZeitAend, we will not know if the view changed
     *  --> NOT incremental x
     *
     *  e.g. backward relation.[$ds: Lagerort, $colAttr: Lagerplatz__Id:COUNT]
     *  Filter:
     *  - ~~Lagerplatz__ZeitAend*~~
     *  - ZeitAend
     *
     *  *As soon as we use an Aggregate Subselect in the SQL the Filter would be applied to that subselect
     *  as well, thus only counting elements since last increment!
     *  --> NOT incremental x
     *
     * @param MetaObjectInterface $dataSheetObject
     * @param DataColumnInterface $column
     * @param string $incrementAttributeAlias
     * @return bool
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::isIncrementalRelationColumn()
     */
    public function isIncrementalRelationColumn(
        MetaObjectInterface $dataSheetObject,
        DataColumnInterface $column,
        string $incrementAttributeAlias) : bool
    {
        $attribute = $column->getAttribute();
        if ($column->isAttribute() === false) {
            return false;
        }

        if ($attribute->getRelationPath()->isEmpty()) {
            return false;
        }

        $relationPaths = $attribute->getRelationPath();
        foreach ($relationPaths->getRelations() as $relation) {
            switch (true) {
                // contains relations without incremental attribute
                case $relation->getLeftObject()->hasAttribute($incrementAttributeAlias) === false ||
                    $relation->getRightObject()->hasAttribute($incrementAttributeAlias) === false:
                case $relation->isReverseRelation():
                    return false;
            }
        }

        return true;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::getIncrementAttribute()
     */
    public function getIncrementAttribute() : ?MetaAttributeInterface
    {
        $obj = $this->getMetaObject();
        $tsBehavior = $obj->getBehaviors()->getByPrototypeClass(TimeStampingBehavior::class)->getFirst();
        return $tsBehavior?->getUpdatedOnAttribute();
    }
    
    /**
     *
     * @return array
     */
    public function getBinaryDataTypeColumnNames() : array
    {
        // TODO How to get download urls for binary columns?
        return [];
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::getImageUrlDataTypeColumnNames()
     */
    public function getImageUrlDataTypeColumnNames() : array
    {
        $columnsArray = [];
        $columns = $this->getDataSheet()->getColumns();
        foreach ($columns as $column) {
            $columnDataType = $column->getDataType();
            if($columnDataType !== null && $columnDataType instanceof ImageUrlDataType) {
                array_push($columnsArray, $column->getName());
            }
        }
        return $columnsArray;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::getIncrementValue()
     */
    public function getIncrementValue(DataSheetInterface $data, bool $assumeDataIsFresh = true) : ?string
    {
        $incrAttr = $this->getIncrementAttribute();
        $incrType = $incrAttr->getDataType();
        switch (true) {
            case $incrType instanceof DateTimeDataType:
                $newIncrement = DateTimeDataType::now();
                break;
            case $incrType instanceof NumberDataType:
                // TODO find a use case to test this!
                $incrCol = $data->getColumns()->getByAttribute($incrAttr);
                $newIncrement = $incrCol->aggregate(AggregatorFunctionsDataType::MAX) + 1;
                break;
        }
        return $newIncrement;
    }
}