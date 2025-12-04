<?php

namespace exface\Core\CommonLogic\Utils;

use exface\Core\Exceptions\DataTrackerException;
use exface\Core\Interfaces\DataTrackerInterface;

/**
 * @inheritDoc
 * @see DataTrackerInterface
 */
class DataTracker implements DataTrackerInterface
{
    private array $baseData = [];
    private array $currentData = [];
    private int $currentDataLength;
    private array $dataVersions = [];
    private int $latestVersion = 0;

    /**
     * @param array $data
     * @param bool  $deduplicate
     */
    public function __construct(array $data, bool $deduplicate = false)
    {
        if(!empty($duplicates = $this->getDuplicates($data))) {
            if($deduplicate) {
                $data = array_unique($data);
            } else {
                throw new DataTrackerException('Key data has duplicate entries!', $duplicates);
            }
        }

        $count = count($data);
        $this->baseData = $this->currentData = $data;
        $this->dataVersions = array_fill(0, $count, 0);
        $this->currentDataLength = $count;
        $this->latestVersion = 0;
    }

    /**
     * Returns all entries that are duplicates within a given array, ignoring
     * the first value in a duplicates set (i.e. if your array contains two identical
     * entries, this function will return the second).
     * 
     * @param array $data
     * @return array
     */
    protected function getDuplicates(array $data) : array
    {
        return array_diff_key($data, array_unique(array_map('serialize', $data)));
    }

    /**
     * @inheritDoc
     * @see DataTrackerInterface::recordDataTransform()
     */
    public function recordDataTransform(
        array $fromData,
        array $toData,
        int $preferredVersion = -1
    ) : int
    {
        // If our data sets are empty or don't match up, we abort.
        if(empty($fromData) || count($fromData) !== count($toData)) {
            return -1;
        }

        // Find the internal index for each from-data item.
        $indices = $this->getBaseIndices($fromData, $preferredVersion);
        
        // Update found data after searching for it.
        $version = -1;
        $toDataLength = count($toData);
        
        foreach ($indices as $i => $baseIndex) {
            // Try to match with from data via keys.
            $toIndex = $this->findData(
                $i,
                $fromData[$i],
                $toData,
                $toDataLength
            );
            
            // If we could not match via keys, we match via index.
            // This may be inaccurate if the row order changed, which we cannot detect.
            if($toIndex === false) {
                $toIndex = $i;
            }
            
            // Update data.
            $this->currentData[$baseIndex] = $toData[$toIndex];
            
            // Update version. 
            $dataVersion = ++$this->dataVersions[$baseIndex];
            if($version < $dataVersion) {
                $version = $dataVersion;
            }
        }

        $this->currentDataLength = count($this->currentData);
        
        if($this->latestVersion < $version) {
            $this->latestVersion = $version;
        }
        
        return $version;
    }

    /**
     * @inheritDoc
     * @see DataTrackerInterface::getBaseIndices()
     */
    public function getBaseIndices(
        array $fromData, 
        int $preferredVersion = -1, 
        array &$failedToFind = []
    ) : false|array
    {
        $preferredVersion = $preferredVersion > -1 ? $preferredVersion : $this->latestVersion;
        
        $success = false;
        $found = [];
        $index = 0;
        
        foreach ($fromData as $i => $data) {
            $searchResult = $this->findData(
                $index,
                $data, 
                $this->currentData,
                $this->currentDataLength,
                $preferredVersion,
                $this->dataVersions
            );
            
            // If we can't find a matching data set, we mark it as failed and move on.
            if($searchResult === false) {
                $failedToFind[] = $i;
                continue;
            }

            // If we found a match, cache it.
            $found[$i] = $searchResult;
            $index = $searchResult + 1;
            $success = true;
        }
        
        return $success ? $found : false;
    }

    /**
     * @inheritDoc
     * @see DataTrackerInterface::getBaseData()
     */
    public function getBaseData(array $fromData, array &$failedToFind = []) : false|array
    {
        $indices = $this->getBaseIndices($fromData, -1, $failedToFind);
        return $indices !== false ? $this->getBaseDataFromIndices($indices) : false;
    }

    /**
     * @inheritDoc
     * @see DataTrackerInterface::getBaseDataFromIndices()
     */
    public function getBaseDataFromIndices(array $baseIndices) : false|array
    {
        $success = false;
        $found = [];
        
        foreach ($baseIndices as $baseIndex) {
            if(!empty($data = $this->baseData[$baseIndex])) {
                $found[$baseIndex] = $data;
                $success = true;
            }
        }
        
        return $success ? $found : false;
    }

    /**
     * @inheritDoc
     * @see DataTrackerInterface::getLatestVersionForData()
     */
    public function getLatestVersionForData(array $fromData) : int
    {
        $version = 0;
        
        foreach ($this->getBaseIndices($fromData) as $index) {
            $dataVersion = $this->dataVersions[$index];
            if($dataVersion > $version) {
                $version = $dataVersion;
            }
        }

        return $version;
    }

    /**
     * @param int   $startingIndex
     * @param mixed $needle
     * @param array $hayStack
     * @param int   $hayStackLength
     * @param int   $preferredVersion
     * @param array $dataVersions
     * @return false|int
     */
    protected function findData(
        int   $startingIndex,
        mixed $needle,
        array $hayStack,
        int $hayStackLength,
        int $preferredVersion = -1,
        array $dataVersions = []
    ) : false|int
    {
        if($hayStackLength === 0) {
            return false;
        }
        
        $iterations = 0;
        $index = $startingIndex;
        $maxVersion = -1;
        $bestMatch = -1;

        // We expect this function to be used on large data sets, which is why we are trying
        // to optimize it. Most of the time our data will be ordered and queried sequentially, i.e.
        // we can expect to find our next match close to our last match. So we allow passing in a starting index
        // and search from there, looping around as needed. Best case scenario is a match in one iteration, worst case is 
        // the same as a regular `array_find()`. 
        while ($iterations++ < $hayStackLength) {
            if($index >= $hayStackLength) {
                $index = 0;
            }
            
            $match = true;
            $other = $hayStack[$index];
            foreach ($needle as $key => $value) {
                if( key_exists($key, $other) &&
                    $value !== $other[$key]) {
                    $match = false;
                    break;
                }
            }
            
            if($match) { 
                $version = $dataVersions[$index];
                if($version === $preferredVersion) {
                    return $index;
                }
                
                if($version > $maxVersion) {
                    $maxVersion = $version;
                    $bestMatch = $index;
                }
            }
            
            $index++;
        }
        
        return $bestMatch !== -1 ? $bestMatch : false;
    }
}