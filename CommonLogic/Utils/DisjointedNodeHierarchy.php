<?php

namespace exface\Core\CommonLogic\Utils;

use exface\Core\Exceptions\InvalidArgumentException;

/**
 * Extends the disjointed node tree with explicit hierarchy features. Notably, the hierarchy supports multiple parents for each element.
 */
class DisjointedNodeHierarchy extends DisjointedNodeTree
{
    // TODO geb 2024-10-11: We could add a $nodeToChildren cache as well, if needed.
    private array $nodeToParents = [];

    public function __construct()
    {
        // Strict hierarchies are required.
        parent::__construct(true);
    }

    /**
     * @inheritDoc
     */
    protected function isNewNode(int $node): bool
    {
        if(!parent::isNewNode($node)) {
            return false;
        }

        // Initialize parent lookup.
        $this->nodeToParents[$node] = [];

        return true;
    }

    /**
     * Adds a new node without parents to the hierarchy.
     * 
     * @param int $node
     * @return void
     */
    public function addNode(int $node) : void
    {
        $this->addParent($node, $node);
    }

    /**
     * Adds a parent to a given node. 
     * 
     * Automatically creates and adds new nodes as needed.
     * 
     * @param int $node
     * @param int $parent
     * @return void
     * @throws InvalidArgumentException
     * If `$parent` is by any distance a child of `$node` to prevent circular references.
     */
    public function addParent(int $node, int $parent) : void
    {
        if($this->isChild($node, $parent)) {
            return;
        }
        
        if($this->isChild($parent, $node)) {
            throw new InvalidArgumentException('Parenting '.$node.' to '.$parent.' would create a circular reference! Check your data structure.');
        }

        $hadToCreateNewTree = $this->isNewNode($node) || $this->isNewNode($parent);

        if(!$hadToCreateNewTree && $this->isChild($node, $parent)) {
            return;
        }

        if($node !== $parent) {
            $this->nodeToParents[$node][] = $parent;
        }

        $this->join($node, $parent);
    }

    /**
     * Checks whether a given node is a child of another.
     * 
     * This method is recursive and therefore slow.
     * 
     * @param int $nodeA
     * @param int $nodeB
     * @return bool
     */
    public function isChild(int $nodeA, int $nodeB) : bool
    {
        // If nodeA has no parents, it can't be a child of nodeB.
        if(!key_exists($nodeA, $this->nodeToParents)) {
            return false;
        }
        
        // If these nodes belong to separate trees, they can't be related.
        if(!$this->isConnected($nodeA, $nodeB)) {
            return false;
        }
        
        $parents = $this->nodeToParents[$nodeA];
        // Check if nodeA is a direct child of nodeB.
        if(in_array($nodeB, $parents)) {
            return true;
        }
        
        // If no other check was conclusive, we have to check recursively.
        // TODO geb 2024-10-11: I'm sure there is a smarter way to do this. Recursion is notoriously slow.
        foreach ($parents as $ancestor) {
            if($this->isChild($ancestor, $nodeB)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Returns all known parents of a given node.
     * 
     * @param int $node
     * @return array
     */
    public function getParents(int $node) : array
    {
        return $this->nodeToParents[$node] ?? [];
    }
}