<?php

namespace exface\Core\CommonLogic\Utils;

use exface\Core\Exceptions\DataTrackerException;

/**
 * This class allows you to track and identify data sets across transforms.
 * The tracking is brittle and can break, especially under these circumstances:
 * 
 * - A transform is applied, but not recorded or not recorded accurately.
 * - One or more entries share the same keys.
 * - Base data is queried with data from an in-between state or a state ahead of the last
 * recorded transform.
 * 
 * Provided that the track was not lost, you can request the base data (including its original indices), 
 * by passing any current data into `getBaseData(array)`.
 * 
 * ## Tracking
 * 
 * To start tracking, you need to create a new instance and pass an associative array as the base dataset:
 * 
 * ```
 * 
 * $dataTracker = new DataTracker($data);
 * 
 * ```
 * 
 * Whenever you apply any transforms to the data, you need to record that. Remember to cache the data before
 * transforming it: The tracker needs the unchanged data to find the base set and the transformed data to 
 * record the change.
 * 
 * ```
 * 
 * // You probably need to write some copying logic here.
 * $previousState = $data->copy(); 
 * 
 * // Apply your transform.
 * 
 * $dataTracker->recordTransform($previousState, $newState);
 * 
 * ```
 * 
 * You can wait as long as you like before recording a transform. However, if one of the key columns
 * changes (like changing column names or replacing one column with another) you should record that
 * immediately, or else future records will not track properly. 
 * 
 * ## Retrieval
 * 
 * To retrieve base data, simply call the function you need, for example:
 * 
 * ```
 * 
 * // Get both indices and key data.
 * $baseData = $dataTracker->getBaseData($currentData);
 * 
 * // Get indices only.
 * $originalIndices = $dataTracker->getBaseIndices($currentData);
 * 
 * // Get the latest version.
 * $currentVersion = $dataTracker->getLatestVersionForData($currentData);
 * 
 * ```
 * 
 * Retrieval only works if your current data set has the same state as the last recorded 
 * transform. That also means you cannot retrieve data using in-between states, as the tracker
 * only stores two versions: base and current.
 * 
 * ## Data Versioning
 * 
 * Tracked data may be updated in an aliased fashion, i.e. individual rows may be transformed multiple
 * times, while others may remain unchanged. To avoid potential conflicts, in case a row happens to be
 * transformed in such a way, that it temporarily is a duplicate of another, the tracker uses versions
 * to group rows, according to how many transforms have been recorded for them. 
 * 
 * By default, you won't have to worry about versions. But if you want to transform data row by row, rather 
 * than in batches, or if you run into duplication issues, consider manual version control. Both `recordTransform()`
 * and `getBaseIndices()` accept an optional version parameter. If you enter any integer greater than 0, data comparisons
 * will favor data with that version. You can find the current version for a dataset with either the return value
 * of `recordTransform()` or `getLatestVersionForData()`.
 * 
 */
class DataTracker
{
    private array $baseData = [];
    private array $currentData = [];
    private int $currentDataLength;
    private array $dataVersions = [];
    private int $latestVersion = 0;

    /**
     * @param array $data
     */
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

    /**
     * Returns all entries that are duplicates within a given array, ignoring
     * the first value in a duplicates set (i.e. if your array contains two identical
     * entries, this function will return the second).
     * 
     * @param array $data
     * @return array
     */
    private function getDuplicates(array $data) : array
    {
        return array_diff_key($data, array_unique(array_map('serialize', $data)));
    }

    /**
     * Record a data transform. You can pass an entire data set, a subset or even individual rows.
     * 
     * The transform can be arbitrarily complex. You do not have to record every step along the way,
     * as long as you fulfill these conditions, tracking will succeed:
     * 
     * - Your `$fromData` can be matched with this instances' internal data.
     * - Your `$toData` has the same length as your `$fromData` and both are sorted
     * the same. This is essential, since index matching is used to identify rows across
     * the recorded transform.
     * - Both data sets are deduplicated with respect to their key columns.
     * 
     * @param array $fromData
     * The data as it was BEFORE the transform was applied.
     * @param array $toData
     * The data AFTER the transform was applied.
     * @param int   $preferredVersion
     * You can pass a preferred version for matching your `$fromData`. This can improve accuracy
     * at a minor cost to performance. Only use this feature if you have a good reason to do so.
     * @return int
     * Returns the latest data version AFTER the update among the updated entries.
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

    /**
     * Fetch the base indices for a given data set, if possible.
     * 
     * @param array $fromData
     * @param int   $preferredVersion
     * @return false|array
     */
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
            $found[$i] = $searchResult;
            $index = $searchResult + 1;
            $success = true;
        }
        
        return $success ? $found : false;
    }

    /**
     * Fetch the original data with accurate indices (i.e. row numbers) for a given data set, if possible.
     * 
     * @param array $fromData
     * @return false|array
     */
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

    /**
     * Get the latest version for a given data set.
     * 
     * @param array $fromData
     * @return int
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
     * @param int   $preferredVersion
     * @return false|int
     */
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
        // we can expect to find our next match close to our last match. So we allow passing in a starting index
        // and search from there, looping around as needed. Best case scenario is a match in one iteration, worst case is 
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