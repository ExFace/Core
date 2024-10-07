<?php

namespace exface\Core\CommonLogic\Utils;

use exface\Core\Exceptions\InvalidArgumentException;

class DisjointedNodeHierarchy extends DisjointedNodeTree
{
    private array $nodeToParents = [];

    public function __construct()
    {
        // Strict hierarchies are required.
        parent::__construct(true);
    }

    protected function isNewTree(int $node): bool
    {
        if(!parent::isNewTree($node)) {
            return false;
        }

        // Initialize parent lookup.
        $this->nodeToParents[$node] = [];

        return true;
    }

    public function addNode(int $node) : void
    {
        $this->addParent($node, $node);
    }

    public function addParent(int $node, int $parent) : void
    {
        if($this->isChild($node, $parent)) {
            return;
        }

        if($this->isChild($parent, $node)) {
            throw new InvalidArgumentException('Parenting '.$node.' to '.$parent.' would create a circular reference! Check your data structure.');
        }

        $hadToCreateNewTree = $this->isNewTree($node) || $this->isNewTree($parent);

        if(!$hadToCreateNewTree && $this->isChild($node, $parent)) {
            return;
        }

        if($node !== $parent) {
            $this->nodeToParents[$node][] = $parent;
        }

        $this->join($node, $parent);
    }

    public function isChild(int $node, int $parent) : bool
    {
        if(!key_exists($node, $this->nodeToParents)) {
            return false;
        }

        return in_array($parent, $this->nodeToParents[$node]);
    }

    public function getParents(int $node) : array
    {
        return $this->nodeToParents[$node] ?? [];
    }
}