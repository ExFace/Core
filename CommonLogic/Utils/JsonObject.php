<?php
namespace exface\Core\CommonLogic\Utils;

use exface\Core\Exceptions\UnexpectedValueException;
use JsonPath\JsonPath;

class JsonObject extends \JsonPath\JsonObject
{
    /**
     * Append a new value into all json arrays that match
     * the $jsonPath path.
     *
     * The $value will be inserted at the given $index moving
     * all existing elements starting from that $index further
     * to the end of the array
     *
     * @param string $jsonPath jsonPath
     * @param mixed $value value
     * @param bool $asArray
     * @param int $index will insert at this position
     *
     * @return \JsonPath\JsonObject
     */
    public function insert(string $jsonPath, $value, bool $asArray = false, ?int $index = null)
    {
        if ($index === null) {
            list ($parentPath, $index) = $this->splitArrayPath($jsonPath);
            if ($parentPath === null) {
                throw new UnexpectedValueException('Cannot insert into JSON at "' . $jsonPath . '" - this path does not point to an index in an array!');
            }
            // If the index is not an expression like `?(@.attribute_alias=='Name')`, we need to find
            // all objects, that match the array path, find the index of the element matching that
            // path for every one of them and insert before.
            // For example, inserting to `$..filters[?(@.attribute_alias=='Name')]` will mean, that we
            // insert something to every object, that has a `filters` property. We insert before the
            // element, that has `Name` as attribute_alias - even if that element is located at a different
            // position in every one of them.
            if ($this->isFilterExpression($index)) {
                list($parents, $_) = JsonPath::get($this->getValue(), $parentPath);
                foreach ($parents as $p => &$array) {
                    list($resultsInParent, $_) = JsonPath::get($array, '$[' . $index . ']');
                    foreach ($resultsInParent as $target) {
                        $i = array_search($target, $array, true);
                        if ($i === false) {
                            $array[] = $value;
                        } else {
                            array_splice($array, $i, 0, $asArray ? $value : [$value]);
                        }
                    }
                }
                return $this;
            }
        } else {
            $parentPath = $jsonPath;
        }
        list($result, $_) = JsonPath::get($this->getValue(), $parentPath, true);
        foreach ($result as &$element) {
            if (is_array($element)) {
                if ($index == null) {
                    $element[] = $value;
                } else {
                    array_splice($element, $index, 0, $asArray ? $value : [$value]);
                }
            }
        }
        return $this;
    }

    /**
     * Removes the object at the end of the given JSONpath
     *
     * @param string $jsonPath
     * @return $this
     */
    public function removeObject(string $jsonPath)
    {
        list ($parentPath, $index) = $this->splitArrayPath($jsonPath);
        // If the index is filter expression, remove matching objects from every place, that
        // matches the parent path
        if ($this->isFilterExpression($index)) {
            list($parents, $_) = JsonPath::get($this->getValue(), $parentPath);
            foreach ($parents as $p => &$array) {
                list($resultsInParent, $_) = JsonPath::get($array, '$[' . $index . ']');
                foreach ($resultsInParent as $target) {
                    $i = array_search($target, $array, true);
                    unset($parents[$p][$i]);
                }
            }
        } else {
            $this->remove($parentPath, $index);
        }
        return $this;
    }

    /**
     * Splits a JSONpath pointing to an element array or object element into an array: [[<parent_path>, <key_in_parent>]]
     *
     * Examples:
     *
     * - `$.filters[2]` -> ["$.filters", "2"]
     * - `$.filters[2].asdf` -> ["$.filters", "asdf"]
     * - `$.filters[?(@.attribute_alias=='Name')]` -> ["$.filters", "?(@.attribute_alias=='Name')"]
     * - `$.filters` -> [null, null]
     *
     * @param string $jsonPath
     * @return array
     */
    protected function splitArrayPath(string $jsonPath) : array
    {
        $matches = [];
        // Regular array access
        if (preg_match('/^(.*?)(?:\.|\[)(\d+)\]?$/', $jsonPath, $matches)) {
            return [$matches[1], $matches[2]];
        }
        // Array access via attribute expression: e.g. `$.filters[?(@.attribute_alias=='Name')]`
        if (preg_match('/^(.*?)\[(\?\([^\]]*\))\]$/', $jsonPath, $matches)) {
            $parentPath = $matches[1];
            $filter = $matches[2];
            return [$parentPath, $filter];
        }
        return [null, null];
    }

    /**
     * Returns TRUE if the given string is an attribute expression like `?(@.attribute_alias=='Name')`
     *
     * @param string $index
     * @return bool
     */
    protected function isFilterExpression(string $index) : bool
    {
        return mb_substr($index, 0, 1) === '?';
    }
}