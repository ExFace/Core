<?php

namespace exface\Core\CommonLogic\Utils;

class DisjointedSetGroup
{
    private bool $strictHierarchies = true;

    private array $getParent = [];

    private array $setSize = [];

    public function __construct(bool $strictHierarchy)
    {
        $this->strictHierarchies = $strictHierarchy;
    }

    public function findSet(int $element) : int
    {
        // If element does not belong to a set yet, create a new set for it.
        if(!key_exists($element, $this->getParent)) {
            // Create new set, with the element as its own representative.
            $this->getParent[$element] = $element;
            // Initialize size.
            $this->setSize[$element] = 1;
            // Return element as representative of its newly created set.
            return $element;
        }

        // A set is represented by an element, that is its own parent.
        // We walk through the hierarchy until we find such an element.
        while ($element != $this->getParent[$element]) {
            // If we did not find the representative, we compress the tree by
            // assigning the current element to its grandparent.
            $this->getParent[$element] = $this->getParent[$this->getParent[$element]];
            // Update the current element.
            $element = $this->getParent[$element];
        }

        // Return representative of the set.
        return $element;
    }

    public function join(int $parent, int $child) : int
    {
        // Find sets of elements.
        $setOfParent = $this->findSet($parent);
        $setOfChild = $this->findSet($child);

        // Already joined, return common set.
        if($setOfParent == $setOfChild) {
            return $setOfParent;
        }

        if(!$this->strictHierarchies &&
            $this->setSize[$setOfParent] < $this->setSize[$setOfChild]) {
            // If we do not have strict hierarchies, we can further optimize findSet() by
            // making sure to attach the smaller set to the larger one.
            $parent = $setOfChild;
            $child = $setOfParent;
        } else {
            // If we have strict hierarchies or the tree sizes line up well, we simply join
            // the sets as per the function arguments.
            $parent = $setOfParent;
            $child = $setOfChild;
        }

        // Join the sets.
        $this->getParent[$child] = $parent;
        return $parent;
    }
}