<?php

namespace exface\Core\CommonLogic\Utils;

class DisjointedNodeTree
{
    protected bool $strict = true;

    private array $nodeToRoot = [];

    private array $treeSize = [];

    public function __construct(bool $strict = true)
    {
        $this->strict = $strict;
    }

    protected function isNewTree(int $node) : bool
    {
        // If node already belongs to a tree, cancel the operation.
        if(key_exists($node, $this->nodeToRoot)) {
            return false;
        }

        // Create new tree, with the node as its own root.
        $this->nodeToRoot[$node] = $node;
        // Initialize size.
        $this->treeSize[$node] = 1;

        return true;
    }

    public function findRoot(int $node) : int
    {
        if($this->isNewTree($node)) {
            return $node;
        }

        // The root of a tree is a node, that is its own parent.
        // We walk through the hierarchy until we find such a node.
        while ($node != $this->nodeToRoot[$node]) {
            // If we did not find the root, we compress the tree by
            // assigning the current node to its grandparent.
            $this->nodeToRoot[$node] = $this->nodeToRoot[$this->nodeToRoot[$node]];
            // Move up the hierarchy.
            $node = $this->nodeToRoot[$node];
        }

        // Return root of the tree.
        return $node;
    }

    public function isConnected(int $nodeA, int $nodeB) : bool
    {
        return $this->findRoot($nodeA) === $this->findRoot($nodeB);
    }

    public function join(int $child, int $parent) : int
    {
        // Find root of each node.
        $rootOfParent = $this->findRoot($parent);
        $rootOfChild = $this->findRoot($child);

        // Already joined, return common root.
        if($rootOfParent === $rootOfChild) {
            return $rootOfParent;
        }

        if(!$this->strict &&
            $this->treeSize[$rootOfParent] < $this->treeSize[$rootOfChild]) {
            // If we do not have strict hierarchies, we can further optimize findRoot() by
            // making sure to attach the smaller tree to the larger one.
            $parent = $rootOfChild;
            $child = $rootOfParent;
        } else {
            // If we have strict hierarchies or the tree sizes line up well, we simply join
            // the tree as per the function arguments.
            $parent = $rootOfParent;
            $child = $rootOfChild;
        }

        // Join the trees.
        $this->nodeToRoot[$child] = $parent;
        $this->treeSize[$parent] += $this->treeSize[$child];
        return $parent;
    }
}