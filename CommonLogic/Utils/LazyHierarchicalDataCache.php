<?php

namespace exface\Core\CommonLogic\Utils;

use exface\Core\Exceptions\InvalidArgumentException;

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

    public function getHierarchy() : DisjointedNodeHierarchy
    {
        return $this->hierarchy;
    }

    public function toKey($value) : string
    {
        return serialize($value);
    }

    public function getData($elementId) : mixed
    {
        $nodeKey = $this->toKey($elementId);
        $dataKey = $this->toKey($this->getParents($nodeKey));

        if(!key_exists($dataKey, $this->dataCaches)) {
            return null;
        }

        return $this->dataCaches[$dataKey];
    }

    protected function getParents(string $key) : array
    {
        $node = $this->getNode($key);
        if($node === null || $node < 0) {
            throw new InvalidArgumentException('Cannot get parents of node '.$key.' because it has not been added to the hierarchy yet!');
        }

        return $this->hierarchy->getParents($this->getNode($key));
    }

    public function setData($elementId, $data) : void
    {
        $nodeKey = $this->toKey($elementId);
        $node = $this->nodes[$nodeKey];
        if($node === null || $node < 0){
            throw new InvalidArgumentException('Cannot set data for element '.$elementId.' because it has not been added to the hierarchy yet!');
        }

        $parentKey = $this->getParents($nodeKey);
        $dataKey = $this->toKey($parentKey);
        $this->dataCaches[$dataKey] = $data;
    }

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

            $this->hierarchy->addParent($node, $parent);
        }
    }

    public function getNode(string $key) : int|null
    {
        if(!key_exists($key, $this->nodes)) {
            return null;
        }

        return abs($this->nodes[$key]);
    }

    protected function createNode(string $key) : int
    {
        $node = $this->newNodeIndex++;
        $this->nodes[$key] = -$node;
        $this->hierarchy->addNode($node);

        return -$node;
    }

    protected function enableNode(string $key) : int
    {
        return $this->nodes[$key] = abs($this->nodes[$key]);
    }
}