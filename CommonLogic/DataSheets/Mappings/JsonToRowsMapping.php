<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\DataTypes\JsonDataType;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class JsonToRowsMapping extends AbstractDataSheetMapping 
{
    const JOIN_TYPE_LEFT = 'left';
    
    const JOIN_TYPE_RIGHT = 'right';
    
    private $joinType = self::JOIN_TYPE_LEFT;
    
    private $joinSheet = null;
    
    private $inputSheetKey = null;

    private $jsonColAlias = null;

    private $jsonColExpression = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $jsonCol = $this->getJsonColumn($fromSheet);

        $arrayOfJson = $jsonCol->getValues();
        $newRowsIdColName = $fromSheet->getUidColumn()->getName();
        $newRows = []; 

        foreach ($arrayOfJson as $rowIdx => $jsonString) {
            if ($jsonString === null) {
                continue;
            }
            $json = JsonDataType::decodeJson($jsonString);
            $newRows = array_merge($newRows, $this->flatten($json)); 

            foreach ($newRows as $i => $newRow) {
                $newRows[$i][$newRowsIdColName] = $fromSheet->getUidColumn()->getValue($rowIdx);
            }
        }


        $jsonSheet = DataSheetFactory::createFromObject($fromSheet->getMetaObject());
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
    protected function flatten(array $json) : array
    {
        /*
            TODO:
                - fix export, join not working as expected??
        */

        $result = [];
        $result[] = ["--- NEW JSON ENTRY ---"];

        $parentKeys = $this->getParentKeys($json);
        $uniqueKeys = $this->getUniqueKeys($json);

        // go to innermost arrays and extract their data and parent data
        $rows = $this->extractJsonData($json, [], $uniqueKeys, $parentKeys);

        // re-order rows and fill missing keys with empty strings
        foreach ($rows as $row) {
            $orderedRow = [];
            foreach ($uniqueKeys as $key) {
                // if row has key, use its value
                // otherwise, use empty string
                if (isset($row[$key])) {
                    $orderedRow[$key] = $row[$key];
                } else {
                    $orderedRow[$key] = '';
                }
            }
            $result[] = $orderedRow;
        }

        return $result;
    }

    
    /**
     * Recursive function to find innermost nested json objects, enrich them with parent data,
     * and convert them into a result row
     * 
     * @param array $data
     * @param array $parentData
     * @param array $uniqueKeys
     * @param array $parentKeys
     * @return array<array>
     */
    protected function extractJsonData(array $data, array $parentData = [], array $uniqueKeys, array $parentKeys): array {
        $result = [];
    
        // Loop through each element in the json object
        foreach ($data as $key => $value) {

            // Check if current node is array or simple value
            //     for arrays: mark parent key with X and recurse deeper
            //     for simple values: append value to parent data
            
            if (is_array($value)) {
                
                // mark encountered parent keys with "X" 
                $currentParentData = $parentData;
                if (in_array($key, $parentKeys)) {
                    $currentParentData[$key] = 'X';
                }
    
                // Recursively call the function, passing the current parent data along
                $result = array_merge($result, $this->extractJsonData($value, $currentParentData, $uniqueKeys, $parentKeys));
            } 
            else {
                // If value is not an array, append value to parent data
                $parentData[$key] = $value;
            }
    
            // if innermost array/object is reached, merge all collected data and append to result array
            if (is_array($value) && !empty($value) && !array_filter($value, 'is_array')) {
                
                // merge data (innermost array + parent data) and append to result
                $mergedData = array_merge((array) $parentData, (array) $value);
                $result[] = $mergedData;
            }
        }
    
        return $result;
    }


    /**
     * Returns a list of unique keys within a given JSON object
     * @param array $data
     * @return array
     */
    protected function getUniqueKeys(array $data): array {
        $keys = [];
    
        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                // add key to array if it is not already present
                // exclude the default numeric keys per level
                if (!is_numeric($key)) {
                    if (!in_array($key, $keys)) {
                        $keys[] = $key;
                    }
                }
    
                // If the value is an array or object, search further
                if (is_array($value) || is_object($value)) {
                    $keys = array_merge($keys, $this->getUniqueKeys($value));
                }
            }
        }
    
        return array_unique($keys);
    }


    /**
     * Returns a list of uniqe parent keys (keys to an array) within a given json object
     * 
     * Example: ["Messwerte Förderwasser": [{keys: values},{keys: values}, ...]]
     * -> parent key = "Messwerte Förderwasser"
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


    protected function getJsonColumn(DataSheetInterface $fromSheet) : DataColumnInterface
    {
        return $fromSheet->getColumns()->getByExpression($this->jsonColAlias);
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