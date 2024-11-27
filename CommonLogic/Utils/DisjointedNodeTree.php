<?php

namespace exface\Core\CommonLogic\Utils;

/**
 * A simple implementation of the Disjointed Set Union (renamed for clarity).
 * See https://en.wikipedia.org/wiki/Disjoint-set_data_structure.
 * 
 * Can be extended to provide more functionality if needed. Some ideas can be found here:
 * https://cp-algorithms.com/data_structures/disjoint_set_union.html
 */
class DisjointedNodeTree
{
    protected bool $strict = true;

    private array $nodeToRoot = [];

    private array $treeSize = [];

    /**
     * Create a new node tree instance.
     * 
     * @param bool $strict
     * If TRUE calling `join($child, $parent)` will always result in `$child` being
     * parented to `$parent`. If FALSE the node tree will submit the smaller of the two
     * trees as child, which greatly increases performance.
     */
    public function __construct(bool $strict = true)
    {
        $this->strict = $strict;
    }

    /**
     * If TRUE calling `join($child, $parent)` will always result in `$child` being
     * parented to `$parent`. If FALSE the node tree will submit the smaller of the two
     * trees as child, which greatly increases performance.
     * 
     * @return bool
     */
    public function isStrict() : bool
    {
        return $this->strict;
    }
    
    /**
     * Checks, whether a given node has already been added.
     * 
     * @param int $node
     * @return bool
     */
    protected function isNewNode(int $node) : bool
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

    /**
     * Finds the root of the tree a given node belongs to.
     * 
     * @param int $node
     * @return int
     */
    public function findRoot(int $node) : int
    {
        if($this->isNewNode($node)) {
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

    /**
     * Checks, whether two nodes belong to the same tree.
     * 
     * @param int $nodeA
     * @param int $nodeB
     * @return bool
     */
    public function isConnected(int $nodeA, int $nodeB) : bool
    {
        return $this->findRoot($nodeA) === $this->findRoot($nodeB);
    }

    /**
     * Joins two trees and returns the new root. 
     * 
     * @param int $child
     * @param int $parent
     * @return int
     * The new root of the joined trees. If `isStrict()` is TRUE, this will
     * always be `$parent`. Otherwise, the larger of the two trees will become the 
     * parent (and root).
     */
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