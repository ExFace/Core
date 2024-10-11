<?php

namespace exface\Core\CommonLogic\Utils;

use exface\Core\Behaviors\OrderingBehavior;
use exface\Core\Exceptions\InvalidArgumentException;

/**
 * A data cache that uses an underlying hierarchy to efficiently categorize and access its
 * data. 
 * 
 * All reads and writes are associated with an element in the hierarchy. Data is grouped by sibling
 * relationships, i.e. all elements with the same parents share the same data block in the cache.
 * This is useful if you want to lazily load data from a hierarchy and need to access it later.
 *
 * Used in: @see OrderingBehavior 
 * 
 * ### Examples
 * 
 *          0
 *         / \
 *        1   2
 *         \ / \
 *          3   4
 * 
 * The above hierarchy would have the following caches:
 * | [Parents] | [Elements] | Cache |
 * | --------- | ---------- | ----- |
 * | []        | [0]        | A     |
 * | [0]       | [1,2]      | B     |
 * | [1,2]     | [3]        | C     |
 * | [2]       | [4]        | D     |
 * 
 */
class LazyHierarchicalDataCache
{
    protected DisjointedNodeHierarchy $hierarchy;

    protected array $nodes = [];

    protected array $dataCaches = [];

    private int $newNodeIndex = 0;

    public function __construct()
    {
        $this->hierarchy = new DisjointedNodeHierarchy();
    }

    /**
     * Get a copy of the underlying hierarchy. 
     * 
     * @return DisjointedNodeHierarchy
     */
    public function getHierarchy() : DisjointedNodeHierarchy
    {
        return $this->hierarchy;
    }

    /**
     * Converts a given value into the local key format. This key is not guaranteed
     * to be associated with any data in the cache!
     * 
     * @param $value
     * @return string
     */
    public function toKey($value) : string
    {
        return serialize($value);
    }

    /**
     * Read the data associated with the given `$elementId`. All siblings share a common cache.
     * 
     * @param $elementId
     * The id of the element you wish to query for. You can use any serializable value
     * as element IDs.
     * @return mixed
     * The cached data or NULL if no data has been stored for this element
     * or its siblings.
     */
    public function getData($elementId) : mixed
    {
        $nodeKey = $this->toKey($elementId);

        if(null === ($parents = $this->getParents($nodeKey))) {
            return null;
        }
        $dataKey = $this->toKey($parents);

        return $this->dataCaches[$dataKey];
    }

    /**
     * @param string $key
     * @return array|null
     */
    protected function getParents(string $key) : ?array
    {
        $node = $this->getNode($key);
        if($node === null || $node < 0) {
            return null;
        }

        return $this->hierarchy->getParents($node);
    }

    /**
     * Writes data to the cache of the given `$elementId`. All siblings share a common cache.
     * 
     * Since the cache cannot make assumptions about the data stored,
     * there is no way to directly append data. Instead, use `getData()` first,
     * modify what was returned and then call `setData()` with the modified dataset.
     * 
     * @param $elementId
     * The id of the element you wish to query for. You can use any serializable value
     * as element IDs.
     * @param $data
     * @return void
     */
    public function setData($elementId, $data) : void
    {
        $nodeKey = $this->toKey($elementId);
        $node = $this->getNode($nodeKey);
        if($node === null || $node < 0){
            throw new InvalidArgumentException('Cannot set data for element '.$elementId.' because it has not been added to the hierarchy yet!');
        }

        $parentKey = $this->getParents($nodeKey);
        $dataKey = $this->toKey($parentKey);
        $this->dataCaches[$dataKey] = $data;
    }

    /**
     * Adds a new hierarchy element.
     * 
     * @param       $elementId
     * The id of the element you wish to query for. You can use any serializable value
     * as element IDs.
     * @param array $parentIndices
     * The indices of ALL parents of the new element. You should not change the parents of an element
     * later. Appending parents is possible, but not recommended, since that would create a new cache 
     * and might abandon the old one.
     * @return void
     */
    public function addElement($elementId, array $parentIndices) : void
    {
        $nodeKey = $this->toKey($elementId);
        $node = $this->nodes[$nodeKey];
        if($node === null) {
            $node = $this->createNode($nodeKey);
        }

        if($node < 0) {
            $node = $this->enableNode($nodeKey);
        }

        foreach ($parentIndices as $parentIndex) {
            $parentKey = $this->toKey($parentIndex);
            $parent = $this->getNode($parentKey);
            if($parent === null){
                $parent = $this->createNode($parentKey);
            }

            $this->hierarchy->addParent($node, abs($parent));
        }
    }

    /**
     * Get the node value associated with a key.
     * 
     * @param string $key
     * To get a valid key, call `toKey($elementId)` first.
     * @return int|null
     * Returns the index of the node associated with the given key. If no
     * matching node index was found, NULL is returned instead.
     */
    public function getNode(string $key) : int|null
    {
        if(!key_exists($key, $this->nodes)) {
            return null;
        }

        return $this->nodes[$key];
    }

    /**
     * Creates a new, inactive node. 
     * 
     * Inactive nodes cannot be associated with a data cache, since their parentage is uncertain.
     * Once the parentage of a node is well established, you can enable it by calling `enableNode($node)`.
     * 
     * @param string $key
     * @return int
     */
    protected function createNode(string $key) : int
    {
        $node = $this->newNodeIndex++;
        $this->nodes[$key] = -$node;
        $this->hierarchy->addNode($node);

        return -$node;
    }

    /**
     * Enable a node, so it can be associated with a data cache.
     * 
     * @param string $key
     * @return int
     */
    protected function enableNode(string $key) : int
    {
        return $this->nodes[$key] = abs($this->nodes[$key]);
    }
}