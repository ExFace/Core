<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\Interfaces\DataSheets\DataCacheInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * Keeps data read in cache to avoid multiple reads for the same data
 * 
 * By default, this uses an in-memory array cache, which only lives for a single server request. You can pass any
 * PSR-16 cache to the constructor though to control cache storage from outside.
 * 
 * @author Andrej Kabachnik
 * 
 */
class DataCache implements DataCacheInterface
{
    use ICanBeConvertedToUxonTrait;
    
    private WorkbenchInterface $workbench;
    private CacheInterface $cache;
    private array $keysPerObject = [];
    private array $invalidationListeners = [];
    
    public function __construct(WorkbenchInterface $workbench, ?CacheInterface $cache = null)
    {
        $this->workbench = $workbench;
        $this->cache = $cache ?? new Psr16Cache(new ArrayAdapter());
    }
    
    public function getData(DataSheetInterface $dataSheet) : ?DataSheetInterface
    {
        $data = $this->cache->get($this->getCacheKey($dataSheet));
        if ($data === null) {
            return null;
        }
        return $dataSheet->addRows($data);
    }
    
    public function getOrReadData(DataSheetInterface $dataSheet) : DataSheetInterface
    {
        $data = $this->cache->get($this->getCacheKey($dataSheet));
        if ($data === null) {
            $dataSheet->dataRead();
            $this->setDataForKey($dataSheet, $key);
            return $dataSheet;
        }
        return $dataSheet->addRows($data);
    }
    
    protected function setDataForKey(DataSheetInterface $dataSheet, string $key) : DataSheetInterface
    {
        $this->cache->set($key, $dataSheet->getRows());
        return $this;
    }
    
    public function setData(DataSheetInterface $dataSheet) : DataCacheInterface
    {
        $key = $this->getCacheKey($dataSheet);
        return $this->setDataForKey($dataSheet, $key);
    }
    
    public function deleteData(DataSheetInterface $dataSheet) : DataCacheInterface
    {
        $this->cache->delete($this->getCacheKey($dataSheet));
        return $this;
    }
    
    public function hasData(DataSheetInterface $dataSheet) : bool
    {
        return $this->cache->has($this->getCacheKey($dataSheet));
    }
    
    protected function getObjectsUsed(DataSheetInterface $dataSheet) : array
    {
        $objects = [$dataSheet->getMetaObject()];
        foreach ($dataSheet->getColumns()->getAll() as $column) {
            $expr = $column->getExpressionObj();
            switch (true) {
                case $expr->isMetaAttribute():
                    $attr = $expr->getAttribute();
                    if ($attr->isRelated()) {
                        foreach ($attr->getRelationPath()->getRelations() as $rel) {
                            if (! in_array($rel->getRightObject(), $objects, true)) {
                                $objects[] = $rel->getRightObject();
                            }
                        }
                    }
                    break;
                case $expr->isFormula():
                    // TODO
                    break;
                    
            }
        }
        return $objects;
    }
    
    protected function registerInvalidationListeners(DataSheetEventInterface $cachedSheed) : void
    {
        // TODO invalidate onCreateData, onUpdateData, onDeleteData of any of the getObjectsUsed()
    }
    
    protected function registerInvalidationListenerForObjectChange(MetaObjectInterface $obj) : void
    {
        // TODO register listeners for the given object
    }
    
    public function deleteForObject(MetaObjectInterface $obj) : DataCacheInterface
    {
        // TODO delete cached data based on the given object
    }
    
    public function getCacheKey(DataSheetInterface $dataSheet) : string
    {
        $parts = [];
        $parts[] = 'obj=' . $dataSheet->getMetaObject()->getAliasWithNamespace();
        $parts[] = 'cols=' . $this->buildCacheKeyForColumns($dataSheet);
        $parts[] = 'aggs=' . $this->buildCacheKeyForAggregations($dataSheet);

        if ($dataSheet->isPaged()) {
            $parts[] = 'paged=1';
            $parts[] = 'limit=' . ($dataSheet->getRowsLimit() ?? 'null');
            $parts[] = 'offset=' . $dataSheet->getRowsOffset();
            $parts[] = 'filters=' . $dataSheet->getFilters()->__toString();
            $parts[] = 'sorters=' . $this->buildCacheKeyForSorters($dataSheet);
        } else {
            $parts[] = 'paged=0';
            $parts[] = 'filters=' . $dataSheet->getFilters()->__toString();
            // No sorters needed for unapged sheets
        }

        return implode('&', $parts);
    }

    protected function buildCacheKeyForColumns(DataSheetInterface $dataSheet) : string
    {
        $columns = [];
        foreach ($dataSheet->getColumns() as $column) {
            $columns[] = $column->getExpressionObj()->__toString();
        }
        sort($columns);
        return implode(',', $columns);
    }

    protected function buildCacheKeyForAggregations(DataSheetInterface $dataSheet) : string
    {
        $aggregations = [];
        foreach ($dataSheet->getAggregations() as $aggregation) {
            $aggregations[] = $aggregation->getAttributeAlias();
        }
        sort($aggregations);
        return implode(',', $aggregations);
    }

    protected function buildCacheKeyForSorters(DataSheetInterface $dataSheet) : string
    {
        $sorters = [];
        foreach ($dataSheet->getSorters() as $sorter) {
            // Keep sorter order: it affects paged result slices.
            $sorters[] = $sorter->__toString();
        }
        return implode(',', $sorters);
    }

    /**
     * @inheritDoc
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
}