<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Interface for DataSheet caches
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataCacheInterface extends iCanBeConvertedToUxon, WorkbenchDependantInterface
{
    public const CACHE_GRANULARITY_EXACT = 'exact';
    public const CACHE_GRANULARITY_BROADER_FOR_UNPAGED = 'broader_unpaged';

    public function getData(DataSheetInterface $dataSheet) : ?DataSheetInterface;

    public function getOrReadData(DataSheetInterface $dataSheet) : DataSheetInterface;

    public function setData(DataSheetInterface $dataSheet) : DataCacheInterface;

    public function deleteData(DataSheetInterface $dataSheet) : DataCacheInterface;

    public function hasData(DataSheetInterface $dataSheet) : bool;

    public function getCacheKey(DataSheetInterface $dataSheet) : string;
}