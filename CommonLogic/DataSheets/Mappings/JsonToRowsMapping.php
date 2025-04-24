<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * This mapper transforms JSON objects that are stored in a column (`json_column`) into flattened rows. This allows to use nested JSON data in flat tables like CSV or Excel exports and printing. 
 * 
 * ## How will the data be mapped?
 * 
 * The mapper will create a separate row from each of the JSON objects innermost child-entries 
 * and append them together with their parent data into new rows. If the innermost child object is nested within one or more
 * parent arrays (`"parent-key": [nested child]`), this relationship will be displayed with a marker in the 
 * corresponding column. This marker (`nested_data_marker`) can be customized, and the default is set to 'X'.
 * Empty or non-existing values in the entries will be shown as blank. 
 * 
 * ## Example
 * 
 * This example will map the following JSON Object. The mapper will append one result row per innermost nested object, so the result will have 3 rows in this case. 
 * 
 * 
 * ```
 * 
 * {
 *   "ParentObject": [
 *       {
 *           "ParentKey": "ParentVal1",
 *           "NestedObject1": [
 *               {
 *                   "key1": "val1",
 *                   "key2": "val2",
 *               }
 *           ],
 *           "NestedObject2": [
 *               {
 *                   "key1": "val3",
 *               }
 *           ]
 *       },
 *       {
 *           "ParentKey": "ParentVal2",
 *           "NestedObject1": [
 *               {
 *                   "key1": "val4",
 *                   "key2": "val5",
 *               }
 *           ]
 *       }
 *   ]
 * }
 * 
 * ```
 * 
 * The mapper will then transform this JSON object stored in a single column into the following flattened table structure:
 * 
 * 
 * 
 * | ParentObject | ParentKey | NestedObject1 | NestedObject2 | key1 | key2 |
 * |----------|----------|----------|----------|----------|----------|
 * | X | ParentVal1 | X |   | val1 | val2 |
 * | X | ParentVal1 |   | X | val3 |      |
 * | X | ParentVal2 | X |   | val4 | val5 |
 * 
 * 
 * 
 * ## How can the mapper be used or configured?
 * 
 * An example of how the mapper can be used to export a json column to an excel sheet can be seen below. The property `json_column` specifies which column in the datasheet contains the needed data. 
 * The `nested_data_marker` can be used to customize how the parent keys are shown in the final table
 * 
 * ```
 * 
 *  "buttons": [
 *   {
 *     "caption": "Custom Export",
 *     "action": {
 *       "alias": "exface.Core.ExportXLSX",
 *       "export_mapper": {
 *         "json_to_rows_mappings": [
 *           {
 *             "json_column": "JSONDataColumn",
 *             "nested_data_marker": "Yes" 
 *           }
 *         ]
 *       }
 *     }
 *   }
 *   ]
 *
 * ```
 * 
 * 
 * @author Andrej Kabachnik
 */

class JsonToRowsMapping extends AbstractDataSheetMapping 
{
    private $jsonColAlias = null;

    private $nestedDataMarker = 'X';

    private $jsonColExpression = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $jsonCol = $this->getJsonColumn($fromSheet);
        $nestedMarker = $this->getNestedDataMarker($fromSheet);

        $arrayOfJson = $jsonCol->getValues();
        $uidCol = $fromSheet->getUidColumn();
        $newRowsIdColName = $uidCol->getName();
        $newRows = []; 
        $colKeys = [];

        foreach ($arrayOfJson as $rowIdx => $jsonString) {
            if ($jsonString === null) {
                continue;
            }
            $json = JsonDataType::decodeJson($jsonString);
            $jsonRows = $this->flatten($json, $nestedMarker);
            foreach($jsonRows as $jsonRow) {
                $newRow = [];
                foreach ($jsonRow as $jsonKey => $val) {
                    if (null === $colKeys[$jsonKey] ?? null) {
                        $colKeys[$jsonKey] = DataColumn::sanitizeColumnName($jsonKey);
                    }
                    $newRow[$colKeys[$jsonKey]] = $val;
                }
                $newRow[$newRowsIdColName] = $uidCol->getValue($rowIdx);
                $newRows[] = $newRow;
            }
        }

        $jsonSheet = DataSheetFactory::createFromObject($fromSheet->getMetaObject());
        foreach ($colKeys as $jsonKey => $colName) {
            $jsonSheet->getColumns()->addFromExpression($jsonKey, $colName);
        }
        $jsonSheet->addRows($newRows);
        $toSheet->joinLeft($jsonSheet, $toSheet->getUidColumn()->getName(), $newRowsIdColName);

        return $toSheet;
    }

    /**
     * Flattens a given JSON object into table rows for each nested json object
     * 
     * @param array $json
     * @return array<array>
     */
    protected function flatten(array $json, string $nestedMarker) : array
    {
        // go to innermost arrays and extract their data and parent data
        $parentKeys = $this->getParentKeys($json);
        $rows = $this->extractJsonData($json, [], $parentKeys, $nestedMarker);

        // clean result array and make duplicates unique
        $result = [];
        foreach($rows as $row){
            $resultRow = [];
            $duplicates = [];
            foreach($row as $key => $values){
                // if key is a duplicate, append key with suffix increment
                if ($resultRow[$values[0]] != null) {
                    $duplicates[$values[0]] = ($duplicates[$values[0]] ?? 1) + 1;
                    $resultRow[$values[0] . ' ' . $duplicates[$values[0]]] = $values[1];
                }
                else{
                    $resultRow[$values[0]] = $values[1];
                }
            }
            $result[] = $resultRow;
        }

        return $result;
    }

    
    /**
     * Recursive function to find innermost nested json objects, enrich them with parent data,
     * and convert them into a result row
     * 
     * @param array $data
     * @param array $parentData
     * @param array $parentKeys
     * @param string $nestedMarker
     * @param int $depth
     * @return array[]
     */
    protected function extractJsonData(array $data, array $parentData = [], array $parentKeys, string $nestedMarker, int $depth = 0): array {
        $result = [];
    
        // Loop through each element in the json object
        foreach ($data as $key => $value) {

            // if innermost array/object is reached, merge all collected data and append to result array
            if (is_array($value) && !empty($value) && !array_filter($value, 'is_array')) {
                $prefixedInnermostData = [];
                foreach ($value as $innerKey => $innerValue) {
                    $prefixedInnermostData["depth{$depth}_{$innerKey}"] = [$innerKey, $innerValue];
                }

                // Merge data (innermost array + parent data) and append to result
                $mergedData = array_merge((array) $parentData, $prefixedInnermostData);
                $result[] = $mergedData;
            }

            // Otherwise mark parent key with nested marker and append all key value pairs from current array/level
            if (is_array($value)) {
                //filter data to exclude arrays
                $levelData = array_filter($value, function ($item) {
                    return !is_array($item);
                });

                // Add depth prefix to all keys in the current level data
                $prefixedLevelData = [];
                foreach ($levelData as $levelKey => $levelValue) {
                    $prefixedLevelData["depth{$depth}_{$levelKey}"] = [$levelKey, $levelValue];
                }
            
                // Merge the current parent data with the prefixed level data
                $currentParentData = array_merge((array) $parentData, $prefixedLevelData);
            
                // Mark the current key with the nested marker if it's a parent key
                if (in_array($key, $parentKeys)) {
                    $currentParentData["depth{$depth}_{$key}"] = [$key, $nestedMarker];
                }
            
                // Recursively call the function, passing the updated parent data and incrementing depth
                $result = array_merge($result, $this->extractJsonData($value, $currentParentData, $parentKeys, $nestedMarker, $depth + 1));
            }
        }
    
        return $result;
    }


    /**
     * Returns a list of uniqe parent keys (keys to an array) within a given json object
     * 
     * Example: ["key_123": [{keys: values},{keys: values}, ...]]
     * -> parent key = "key_123"
     * 
     * @param array $entry
     * @return array
     */
    protected function getParentKeys(array $entry): array {
        $parentKeys = [];
    
        foreach ($entry as $key => $value) {
            // if value is an array, consider current key parent key
            if (is_array($value) || is_object($value)) {
                if (!is_numeric($key)) {
                    $parentKeys[] = $key;
                }
    
                // Recursively search for parent keys within the nested array
                $nestedParentKeys = $this->getParentKeys($value);
    
                // Merge nested parent keys with current ones
                $parentKeys = array_merge($parentKeys, $nestedParentKeys);
            }
        }
    
        // return list of all unique parent keys
        return array_unique($parentKeys);
    }



    protected function getNestedDataMarker(DataSheetInterface $fromSheet) : string
    {
        return $this->nestedDataMarker;
    }

    /**
     * Defines how keys of nested JSON ojects should be marked in the resulting table
     * 
     * Example:
     * 
     * ```
     *  [
     *      parentKey: [
     *          nestedKey: {key1: val1, key2: val2}, 
     *          otherNestedKey: {key3: val3, key4: val4}
     *      ]
     *  ]
     * 
     * ```
     * 
     * If nested data marker set to 'X' (default), then the result will be
     * 
     * ```
     *  [{
     *      parentKey: 'X', 
     *      nestedKey: 'X', 
     *      key1: val1, 
     *      key2:val2
     *  }, {
     *      parentKey: 'X', 
     *      otherNestedKey: 'X', 
     *      key3: val3, 
     *      key4:val4
     *  }]
     * 
     * ```
     * 
     * @uxon-property nested_data_marker
     * @uxon-type metamodel:string
     * @uxon-default X
     * 
     * @param string $alias
     * @return JsonToRowsMapping
     */
    protected function setNestedDataMarker(string $alias) : JsonToRowsMapping
    {
        $this->nestedDataMarker = $alias;
        return $this;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $fromSheet
     * @return bool|DataColumnInterface
     */
    protected function getJsonColumn(DataSheetInterface $fromSheet) : DataColumnInterface
    {
        $col = $fromSheet->getColumns()->getByExpression($this->jsonColAlias);
        if (! $col) {
            throw new DataSheetColumnNotFoundError($fromSheet, 'Column "' . $this->jsonColAlias . '" not found in input data for json_to_rows mapping!');
        }
        return $col;
    }
    
    /**
     * Attribute alias or expression to fetch the JSON from the input data
     * 
     * @uxon-property json_column
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return JsonToRowsMapping
     */
    protected function setJsonColumn(string $alias) : JsonToRowsMapping
    {
        $this->jsonColAlias = $alias;
        $this->jsonColExpression = ExpressionFactory::createForObject($this->getMapper()->getFromMetaObject(), $alias);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        return [$this->jsonColExpression];
    }
}