<?php

namespace exface\Core\CommonLogic\Utils;

use exface\Core\Exceptions\DataTrackerException;
use exface\Core\Exceptions\InvalidArgumentException;

class DataTracker
{
    private array $baseData = [];
    private array $currentData = [];
    private int $currentDataLength;
    private array $dataVersions = [];
    private int $latestVersion = 0;
    
    public function __construct(array $data)
    {
        $duplicates = $this->getDuplicates($data);
        if(!empty($duplicates)) {
            throw new DataTrackerException('Key data has duplicate entries!', $duplicates);
        }

        $count = count($data);
        $this->baseData = $this->currentData = $data;
        $this->dataVersions = array_fill(0, $count, 0);
        $this->currentDataLength = $count;
        $this->latestVersion = 0;
    }
    
    private function getDuplicates(array $data) : array
    {
        return array_diff_key($data, array_unique(array_map('serialize', $data)));
    }

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
        foreach ($indices as $i => $index) {
            // Update data.
            $this->currentData[$index] = $toData[$i];
            
            // Update version.
            $dataVersion = ++$this->dataVersions[$index];
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
    
    public function getBaseIndices(array $fromData, int $preferredVersion = -1) : false|array
    {
        $preferredVersion = $preferredVersion > -1 ? $preferredVersion : $this->latestVersion;
        
        $success = false;
        $found = [];
        $index = 0;
        
        foreach ($fromData as $i => $data) {
            $searchResult = $this->findData($index, $data, $preferredVersion);
            // If we can't find a matching data set, we cannot record a transform for this item.
            if($searchResult === false) {
                continue;
            }

            // If we found a match, cache it.
            $found[$i] = $index = $searchResult;
            $success = true;
        }
        
        return $success ? $found : false;
    }
    
    public function getBaseData(array $fromData) : false|array
    {
        $indices = $this->getBaseIndices($fromData);
        return $indices !== false ? $this->getBaseDataFromIndices($indices) : false;
    }
    
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
    
    private function findData(
        int   $startingIndex,
        mixed $needle,
        int $preferredVersion
    ) : false|int
    {
        if($this->currentDataLength === 0) {
            return false;
        }
        
        $iterations = 0;
        $index = $startingIndex;
        $maxVersion = -1;
        $bestMatch = -1;

        // We expect this function to be used on large data sets, which is why we are trying
        // to optimize it. Most of the time our data will be ordered and queried sequentially, i.e.
        // we can expect to find our next match close to our last match. So we allow passing in a starting
        // and start searching from there, looping around if needed. Best case scenario is one iteration, worst case is 
        // the same as a regular `array_find()`. 
        while ($iterations++ < $this->currentDataLength) {
            if($index >= $this->currentDataLength) {
                $index = 0;
            }
            
            if($needle === $this->currentData[$index]) {
                $version = $this->dataVersions[$index];
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