<?php

namespace exface\Core\Interfaces;

/**
 * DataTrackers allow you to track and identify data sets across transforms.
 * The tracking is brittle and can break, especially under these circumstances:
 *
 * - You recorded a transform that changed BOTH the row order AND one or more key columns. This particular
 * issue can be avoided by always recording order changes immediately, if possible.
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
 * $previousState = ... // Cache or copy the state before transforming.
 *
 * $newState = ... // Apply your transform.
 *
 * $dataTracker->recordTransform($previousState, $newState);
 *
 * ```
 *
 * You can wait as long as you like before recording a transform. However, if one of the key columns
 * or the row order is changed (like changing column names or replacing one column with another) you should 
 * record that immediately, or else future records will not track properly.
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
 * // Get the latest version number.
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
 * times ahead of other rows. To avoid potential conflicts, in case a row happens to be
 * transformed in such a way, that it temporarily is a duplicate of another, the tracker uses version numbers
 * to group rows, according to how many transforms have been recorded for them.
 *
 * By default, you won't have to worry about versions. But if you want to transform data row by row, rather
 * than in batches, or if you run into duplication issues, consider manual version control. Both `recordTransform()`
 * and `getBaseIndices()` accept an optional version parameter. If you enter any integer greater than 0, data comparisons
 * will favor data with that version. You can find the current version for a dataset with either the return value
 * of `recordTransform()` or `getLatestVersionForData()`.
 *
 */
interface DataTrackerInterface
{
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
    ) : int;

    /**
     * Fetch the base indices for a given data set, if possible.
     *
     * @param array $fromData
     * @param int   $preferredVersion
     * @param array $failedToFind
     * Any indices in the `$fromData` that could not be traced back to
     * their base data will be collected in this array.
     * @return false|array
     */
    public function getBaseIndices(
        array $fromData,
        int $preferredVersion = -1,
        array &$failedToFind = []
    ) : false|array;

    /**
     * Fetch the original data with accurate indices (i.e. row numbers) for a given data set, if possible.
     *
     * @param array $fromData
     * @param array $failedToFind
     * Any rows in the `$fromData` that could not be traced back to
     * their base data will be collected in this array.
     * @return false|array
     */
    public function getBaseData(array $fromData, array &$failedToFind = []) : false|array;

    /**
     * Fetches all base data for the given indices.
     *
     * NOTE: Indices must be base indices, use `getBaseIndices(array, int, array)` to retrieve them.
     *
     * @param array $baseIndices
     * @return false|array
     * @see DataTracker::getBaseIndices()
     */
    public function getBaseDataFromIndices(array $baseIndices) : false|array;

    /**
     * Get the latest version number for a given data set.
     *
     * @param array $fromData
     * @return int
     */
    public function getLatestVersionForData(array $fromData) : int;
}