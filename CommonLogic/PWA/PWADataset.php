<?php
namespace exface\Core\CommonLogic\PWA;

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
use exface\Core\DataTypes\BinaryDataType;
use exface\Core\DataTypes\ImageUrlDataType;
use exface\Core\DataTypes\StringDataType;

class PWADataset implements PWADatasetInterface
{
    use ImportUxonObjectTrait;
    
    private $pwa = null;
    
    private $dataSheet = null;
    
    private $actions =  [];
    
    private $uid = null;
    
    public function __construct(PWAInterface $pwa, DataSheetInterface $dataSheet, string $uid = null)
    {
        $this->pwa = $pwa;
        $this->dataSheet = $dataSheet;
        $this->uid = $uid;
    }
    
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
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
    
    public function getDataSheet(): DataSheetInterface
    {
        return $this->dataSheet;
    }
    
    /**
     * 
     * @param PWADataset $dataSet
     * @param unknown $dataType
     * @return array
     */
    public function getBinaryDataTypeColumnNames(PWADataset $dataSet) : array
    {
        $columnsArray = [];
        $columns = $dataSet->getDataSheet()->getColumns();
        foreach ($columns as $column) {
            $columnDataType = $column->getDataType();
            if($columnDataType !== null && $columnDataType instanceof BinaryDataType) {
                    array_push($columnsArray, $column->getName());
            }
        }
        return $columnsArray;
    }
    
    /**
     *
     * @param PWADataset $dataSet
     * @param unknown $dataType
     * @return array
     */
    public function getImageUrlDataTypeColumnNames(PWADataset $dataSet) : array
    {
        $columnsArray = [];
        $columns = $dataSet->getDataSheet()->getColumns();
        foreach ($columns as $column) {
            $columnDataType = $column->getDataType();
            if($columnDataType !== null && $columnDataType instanceof ImageUrlDataType) {
                array_push($columnsArray, $column->getName());
            }
        }
        return $columnsArray;
    }

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
            $ds->getFilters()->addConditionFromAttribute($incrementAttr, $incrementValue, ComparatorDataType::GREATER_THAN_OR_EQUALS);
        }
        
        $ds->dataRead($limit, $offset);
        return $ds;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::isIncremental()
     */
    public function isIncremental() : bool
    {
        return $this->findIncrementAttribute() !== null;
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
        if ($tsBehavior === null) {
            return null;
        }
        return $tsBehavior->getUpdatedOnAttribute();
    }
}